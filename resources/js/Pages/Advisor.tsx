import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
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

function ProfileForm({ profile, goalObjective }: { profile: InvestorProfile | null; goalObjective: string | null }) {
    const form = useForm({
        horizon: profile?.horizon ?? '',
        risk_tolerance: profile?.risk_tolerance ?? '',
        objective: profile?.objective ?? '',
        target_allocation: profile?.target_allocation ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/advisor/profile', { preserveScroll: true });
    };

    return (
        <Card>
            <CardHeader className="pb-3">
                <CardTitle className="text-sm flex items-center gap-2">
                    <UserCog className="w-4 h-4" />
                    Il tuo profilo investitore
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-xs text-muted-foreground mb-3">
                    Questo contesto rende l&apos;analisi tua, non generica. L&apos;allocazione target è opzionale: se non ce l&apos;hai, lascia vuoto e il consulente può aiutarti a ragionarci.
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
                        <p className="text-xs text-muted-foreground">
                            Se vuoto, il consulente usa l&apos;obiettivo dalla sezione Obiettivo. Compila qui solo per sovrascriverlo.
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Allocazione target <span className="text-muted-foreground">(opzionale)</span></Label>
                        <Input
                            value={form.data.target_allocation}
                            onChange={(e) => form.setData('target_allocation', e.target.value)}
                            placeholder="Se vuoto, usa le percentuali della sezione Obiettivo"
                        />
                    </div>
                    <Button type="submit" size="sm" disabled={form.processing}>
                        {form.processing ? 'Salvataggio…' : 'Salva profilo'}
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}

export default function Advisor({ configured, profile, goalObjective }: Props) {
    const [report, setReport] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const generate = async () => {
        setLoading(true);
        setError(null);
        try {
            // Inertia/Laravel issues an XSRF-TOKEN cookie; echo it back as the
            // X-XSRF-TOKEN header so the POST passes CSRF (no Blade meta tag).
            const xsrf = decodeURIComponent(
                document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
            );
            const res = await fetch('/advisor/generate', {
                method: 'POST',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            });
            const data = await res.json();
            if (!res.ok) {
                setError(data.error ?? 'Generazione non riuscita.');
            } else {
                setReport(data.report);
            }
        } catch {
            setError('Errore di rete durante la generazione.');
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
                        <ProfileForm profile={profile} goalObjective={goalObjective} />

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
                                <CardContent className="py-5">
                                    <div className="whitespace-pre-wrap text-sm leading-relaxed">{report}</div>
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
        </>
    );
}

Advisor.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
