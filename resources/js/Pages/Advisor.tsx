import { useState } from 'react';
import axios from 'axios';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/Components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Markdown } from '@/Components/ui/Markdown';
import { Sparkles, AlertTriangle, Loader2, UserCog } from 'lucide-react';

interface InvestorProfile {
    horizon: string | null;
    risk_tolerance: string | null;
    objective: string | null;
    target_allocation: string | null;
}

interface Props {
    configured: boolean;
    profile: InvestorProfile | null;
    goalObjective: string | null;
}

const HORIZON_LABELS: Record<string, string> = { short: 'Breve', medium: 'Medio', long: 'Lungo' };
const RISK_LABELS: Record<string, string> = { low: 'Bassa', medium: 'Media', high: 'Alta' };
const REPORT_KEY = 'advisor-report';

function ProfileDialog({
    open,
    onClose,
    profile,
    goalObjective,
}: {
    open: boolean;
    onClose: () => void;
    profile: InvestorProfile | null;
    goalObjective: string | null;
}) {
    const form = useForm({
        horizon: profile?.horizon ?? '',
        risk_tolerance: profile?.risk_tolerance ?? '',
        objective: profile?.objective ?? '',
        target_allocation: profile?.target_allocation ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/advisor/profile', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Il tuo profilo investitore</DialogTitle>
                </DialogHeader>
                <p className="text-xs text-muted-foreground">
                    Questo contesto rende l&apos;analisi tua, non generica. Obiettivo e allocazione sono opzionali: se vuoti, il consulente usa quelli della sezione Obiettivo.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label className="text-xs">Orizzonte temporale</Label>
                            <Select value={form.data.horizon} onValueChange={(v) => form.setData('horizon', v)}>
                                <SelectTrigger><SelectValue placeholder="Seleziona" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="short">Breve (&lt; 3 anni)</SelectItem>
                                    <SelectItem value="medium">Medio (3-10 anni)</SelectItem>
                                    <SelectItem value="long">Lungo (10+ anni)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Tolleranza al rischio</Label>
                            <Select value={form.data.risk_tolerance} onValueChange={(v) => form.setData('risk_tolerance', v)}>
                                <SelectTrigger><SelectValue placeholder="Seleziona" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Bassa</SelectItem>
                                    <SelectItem value="medium">Media</SelectItem>
                                    <SelectItem value="high">Alta</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Obiettivo principale <span className="text-muted-foreground">(opzionale)</span></Label>
                        <Input
                            value={form.data.objective}
                            onChange={(e) => form.setData('objective', e.target.value)}
                            placeholder={goalObjective ? `Da Obiettivo: ${goalObjective}` : 'es. indipendenza finanziaria, pensione, casa'}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Allocazione target <span className="text-muted-foreground">(opzionale)</span></Label>
                        <textarea
                            value={form.data.target_allocation}
                            onChange={(e) => form.setData('target_allocation', e.target.value)}
                            placeholder="Se vuoto, usa le percentuali della sezione Obiettivo. Es: 60% azioni, 20% obbligazioni, 20% liquidità"
                            rows={2}
                            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvataggio…' : 'Salva profilo'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ProfileSummary({ profile, onEdit }: { profile: InvestorProfile | null; onEdit: () => void }) {
    const parts: string[] = [];
    if (profile?.horizon) parts.push(`Orizzonte: ${HORIZON_LABELS[profile.horizon] ?? profile.horizon}`);
    if (profile?.risk_tolerance) parts.push(`Rischio: ${RISK_LABELS[profile.risk_tolerance] ?? profile.risk_tolerance}`);
    if (profile?.objective) parts.push(`Obiettivo: ${profile.objective}`);

    return (
        <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-muted/30 px-3 py-2">
            <div className="flex items-center gap-2 min-w-0 text-xs text-muted-foreground">
                <UserCog className="w-3.5 h-3.5 flex-shrink-0" />
                <span className="truncate">
                    {parts.length > 0 ? parts.join(' · ') : 'Profilo non compilato — l’analisi sarà più mirata se lo imposti.'}
                </span>
            </div>
            <Button variant="ghost" size="sm" className="flex-shrink-0 h-7 text-xs" onClick={onEdit}>
                {parts.length > 0 ? 'Modifica' : 'Compila'}
            </Button>
        </div>
    );
}

export default function Advisor({ configured, profile, goalObjective }: Props) {
    // Persist the last report in localStorage so it survives a refresh — a
    // simple stopgap until chat sessions give it a proper home. Kept per
    // browser; shown with its generation date so its age is clear.
    const [saved, setSaved] = useState<{ text: string; generatedAt: string } | null>(() => {
        try {
            const raw = localStorage.getItem(REPORT_KEY);
            return raw ? (JSON.parse(raw) as { text: string; generatedAt: string }) : null;
        } catch {
            return null;
        }
    });
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [profileOpen, setProfileOpen] = useState(false);

    const report = saved?.text ?? null;

    const generate = async () => {
        setLoading(true);
        setError(null);
        try {
            // Use axios (configured globally in bootstrap.js): it reads the
            // XSRF-TOKEN cookie and sets the header automatically, so the POST
            // passes CSRF the same way Inertia's own requests do.
            const { data } = await axios.post('/advisor/generate');
            const entry = { text: data.report as string, generatedAt: new Date().toISOString() };
            setSaved(entry);
            try {
                localStorage.setItem(REPORT_KEY, JSON.stringify(entry));
            } catch {
                // Storage full or unavailable — keep it in memory at least.
            }
        } catch (e) {
            const message = axios.isAxiosError(e) && typeof e.response?.data?.error === 'string'
                ? e.response.data.error
                : 'Generazione non riuscita.';
            setError(message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <>
            <Head title="Consulente AI" />
            <div className="p-4 space-y-4 max-w-[900px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={Sparkles}
                    title="Consulente AI"
                    subtitle="Una lettura del tuo portafoglio basata sulle tue metriche"
                />

                {!configured ? (
                    <Card>
                        <CardContent className="py-8 text-center space-y-2">
                            <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto" />
                            <p className="text-sm text-muted-foreground max-w-md mx-auto">
                                Il consulente AI non è configurato. Imposta un modello locale (Ollama) tramite <code>OLLAMA_MODEL</code> per generare l&apos;analisi.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <ProfileSummary profile={profile} onEdit={() => setProfileOpen(true)} />

                        <div className="flex items-center gap-3">
                            <Button onClick={generate} disabled={loading}>
                                {loading ? (
                                    <>
                                        <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                        Analisi in corso…
                                    </>
                                ) : (
                                    <>
                                        <Sparkles className="w-4 h-4 mr-2" />
                                        {report ? 'Rigenera analisi' : 'Genera analisi'}
                                    </>
                                )}
                            </Button>
                            {loading && (
                                <span className="text-xs text-muted-foreground">
                                    Il modello locale può impiegare qualche decina di secondi.
                                </span>
                            )}
                        </div>

                        {error && (
                            <Card>
                                <CardContent className="py-4 flex items-start gap-2 text-sm text-red-500">
                                    <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                                    <span>{error}</span>
                                </CardContent>
                            </Card>
                        )}

                        {report && (
                            <Card>
                                <CardContent className="py-2">
                                    {saved?.generatedAt && (
                                        <p className="text-xs text-muted-foreground pt-2">
                                            Analisi generata il {new Date(saved.generatedAt).toLocaleString('it-IT', {
                                                day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit',
                                            })}
                                        </p>
                                    )}
                                    <Markdown content={report} />
                                </CardContent>
                            </Card>
                        )}

                        {!report && !loading && !error && (
                            <p className="text-sm text-muted-foreground">
                                Premi «Genera analisi» per ottenere una lettura del tuo portafoglio.
                            </p>
                        )}
                    </>
                )}
            </div>

            <ProfileDialog
                open={profileOpen}
                onClose={() => setProfileOpen(false)}
                profile={profile}
                goalObjective={goalObjective}
            />
        </>
    );
}

Advisor.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
