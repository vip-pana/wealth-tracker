import { useState, useEffect, useRef } from 'react';
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
import { useToast } from '@/lib/toast';
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

type Status = 'idle' | 'pending' | 'done' | 'failed';

interface StatusResponse {
    status: Status;
    content?: string | null;
    error?: string | null;
    generated_at?: string | null;
}

export default function Advisor({ configured, profile, goalObjective }: Props) {
    const pushToast = useToast();
    const [status, setStatus] = useState<Status>('idle');
    const [report, setReport] = useState<string | null>(null);
    const [generatedAt, setGeneratedAt] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [profileOpen, setProfileOpen] = useState(false);
    const [refreshKey, setRefreshKey] = useState(0);

    const loading = status === 'pending';

    // Source of truth is the server: the report is generated by a queued job
    // and persisted, so it survives navigating away or closing the tab. On
    // mount we read the current state; while pending we poll until it resolves.
    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | undefined;
        let cancelled = false;

        const tick = async () => {
            try {
                const { data } = await axios.get<StatusResponse>('/advisor/status');
                if (cancelled) return;

                setStatus(data.status);
                setReport(data.content ?? null);
                setGeneratedAt(data.generated_at ?? null);
                setError(data.status === 'failed' ? (data.error ?? 'Generazione non riuscita.') : null);

                if (data.status === 'pending') {
                    timer = setTimeout(tick, 2500);
                }
            } catch {
                // Transient poll failure: stop quietly, the user can retry.
            }
        };

        void tick();

        return () => {
            cancelled = true;
            if (timer) clearTimeout(timer);
        };
    }, [refreshKey]);

    // Toast on a real pending→done/failed transition. Driven by the rendered
    // `status` state (not the poll closure) so it's immune to the effect
    // restarting and to Strict Mode double-mounts. Uses the global toast stack
    // so it looks and lives like every other toast in the app.
    const prevStatus = useRef<Status>(status);
    useEffect(() => {
        const from = prevStatus.current;
        prevStatus.current = status;
        if (from === 'pending' && status === 'done') {
            pushToast('Analisi completata.', 'success');
        } else if (from === 'pending' && status === 'failed') {
            pushToast('Generazione non riuscita.', 'error');
        }
    }, [status, pushToast]);

    const generate = async () => {
        setError(null);
        try {
            await axios.post('/advisor/generate');
            setStatus('pending');
            setReport(null);
            setRefreshKey((k) => k + 1); // restart the poll loop
        } catch (e) {
            const message = axios.isAxiosError(e) && typeof e.response?.data?.error === 'string'
                ? e.response.data.error
                : 'Generazione non riuscita.';
            setError(message);
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
                                    {generatedAt && (
                                        <p className="text-xs text-muted-foreground pt-2">
                                            Analisi generata il {new Date(generatedAt).toLocaleString('it-IT', {
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
