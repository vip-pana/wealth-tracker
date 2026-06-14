import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Sparkles, AlertTriangle, Loader2 } from 'lucide-react';

interface Props {
    configured: boolean;
}

export default function Advisor({ configured }: Props) {
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
