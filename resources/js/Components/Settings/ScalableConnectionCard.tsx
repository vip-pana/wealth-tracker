import { useForm, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { RefreshCw, Link2, Unlink, AlertTriangle, CandlestickChart, ExternalLink, Copy, Check, X } from 'lucide-react';
import { brokerFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import { TransactionAssetsBlock } from '@/Components/Settings/TransactionAssetsBlock';
import type { ScalableLoginState, ScalableState, TransactionAsset } from '@/Components/Settings/types';

export function ScalableConnectionCard({ state, transactionAssets }: { state: ScalableState; transactionAssets: TransactionAsset[] }) {
    const refresh = useForm({});
    const login = useForm({});
    const logout = useForm({});
    const freshness = brokerFreshness(state.last_sync_at);
    const failed = state.last_sync_status === 'failed';
    const needsLogin = state.cli_logged_in === false;

    const cancel = useForm({});
    const [loginFlow, setLoginFlow] = useState<ScalableLoginState>(state.login);
    const [timedOut, setTimedOut] = useState(false);
    const inProgress = !timedOut && (loginFlow.status === 'pending' || loginFlow.status === 'url_issued');
    const [copied, setCopied] = useState(false);

    // Poll the CLI login status while a login is in flight; stop on a terminal
    // state. On completion, refresh the page props so the badge turns live.
    // A device code expires after a few minutes, so cap the wait: if no
    // confirmation lands, stop polling and surface a retry instead of spinning
    // forever on an orphaned "In attesa di conferma…".
    useEffect(() => {
        if (!inProgress) {
            return;
        }
        const controller = new AbortController();
        const id = setInterval(() => {
            void fetch('/scalable/cli/login/status', { signal: controller.signal, headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((next: ScalableLoginState) => {
                    setLoginFlow(next);
                    if (next.status === 'complete') {
                        router.reload({ only: ['scalable'] });
                    }
                })
                .catch(() => undefined);
        }, 2000);
        const giveUp = setTimeout(() => setTimedOut(true), 5 * 60 * 1000);
        return () => {
            clearInterval(id);
            clearTimeout(giveUp);
            controller.abort();
        };
    }, [inProgress]);

    const startCliLogin = () => {
        setTimedOut(false);
        setLoginFlow({ status: 'pending', url: null, user_code: null, error: null, started_at: null });
        login.post('/scalable/cli/login', { preserveScroll: true });
    };

    const cancelCliLogin = () => {
        setTimedOut(false);
        setLoginFlow({ status: 'idle', url: null, user_code: null, error: null, started_at: null });
        cancel.post('/scalable/cli/login/cancel', { preserveScroll: true });
    };

    const copyCode = (code: string) => {
        void navigator.clipboard.writeText(code).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
                <div>
                    <CardTitle className="text-base flex items-center gap-1.5">
                        <CandlestickChart className="w-4 h-4 text-indigo-400" aria-hidden />
                        Scalable Capital
                    </CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Sincronizza saldi e posizioni dal broker (sola lettura) tramite la CLI ufficiale Scalable. Si aggiorna ogni giorno alle 06:00 insieme ai prezzi.
                    </p>
                </div>
                {state.configured && (
                    <div className="flex items-center gap-2">
                        {needsLogin && !inProgress && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={startCliLogin}
                                disabled={login.processing}
                                title="Avvia il login: apri il link mostrato e inserisci il codice + 2FA"
                            >
                                <Link2 className={`w-4 h-4 mr-1 ${login.processing ? 'animate-pulse' : ''}`} />
                                {login.processing ? 'Avvio…' : 'Collega / Riconnetti'}
                            </Button>
                        )}
                        {state.cli_logged_in === true && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => refresh.post('/scalable/refresh', { preserveScroll: true })}
                                disabled={refresh.processing}
                            >
                                <RefreshCw className={`w-4 h-4 mr-1 ${refresh.processing ? 'animate-spin' : ''}`} />
                                Sincronizza ora
                            </Button>
                        )}
                        {state.cli_logged_in === true && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => logout.post('/scalable/cli/logout', { preserveScroll: true })}
                                disabled={logout.processing}
                                title="Rimuove la sessione Scalable salvata dalla CLI"
                            >
                                <Unlink className={`w-4 h-4 mr-1 ${logout.processing ? 'animate-pulse' : ''}`} />
                                Scollega
                            </Button>
                        )}
                    </div>
                )}
            </CardHeader>
            <CardContent className="space-y-2">
                {!state.configured ? (
                    <p className="text-xs text-amber-500">
                        Sincronizzazione Scalable non configurata (abilita SCALABLE_CLI_ENABLED).
                    </p>
                ) : (
                    <>
                        <div className="flex items-center gap-1.5 text-sm">
                            {state.cli_logged_in === false ? (
                                <span className="inline-flex items-center gap-1.5 text-amber-500">
                                    <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                    Sessione scaduta, riconnetti
                                </span>
                            ) : failed ? (
                                <span className="inline-flex items-center gap-1.5 text-destructive" title={state.last_sync_error ?? undefined}>
                                    <span className="w-1.5 h-1.5 rounded-full bg-destructive" />
                                    Ultimo sync fallito
                                </span>
                            ) : state.last_sync_at === null ? (
                                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <span className="w-1.5 h-1.5 rounded-full bg-muted-foreground" />
                                    Mai sincronizzato
                                </span>
                            ) : (
                                <span className={cn('inline-flex items-center gap-1.5', freshness.stale ? 'text-amber-500' : 'text-emerald-400')}>
                                    <span className={cn('w-1.5 h-1.5 rounded-full', freshness.stale ? 'bg-amber-500' : 'bg-emerald-500')} />
                                    {freshness.stale ? 'Sincronizzazione ferma' : 'Connesso'} · {freshness.label}
                                </span>
                            )}
                        </div>

                        {inProgress && (
                            <div className="rounded-md border border-border bg-muted/30 p-3 space-y-2">
                                {loginFlow.status === 'url_issued' && loginFlow.url ? (
                                    <>
                                        <a
                                            href={loginFlow.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 text-sm text-indigo-400 hover:underline"
                                        >
                                            <ExternalLink className="w-3.5 h-3.5" aria-hidden />
                                            Apri Scalable per confermare
                                        </a>
                                        {loginFlow.user_code && (
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-muted-foreground">Codice:</span>
                                                <code className="rounded bg-background px-2 py-0.5 font-mono text-sm tracking-wider">{loginFlow.user_code}</code>
                                                <button
                                                    type="button"
                                                    onClick={() => copyCode(loginFlow.user_code as string)}
                                                    className="text-muted-foreground hover:text-foreground"
                                                    title="Copia il codice"
                                                >
                                                    {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" aria-hidden /> : <Copy className="w-3.5 h-3.5" aria-hidden />}
                                                </button>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <RefreshCw className="w-3 h-3 animate-spin" aria-hidden />
                                                In attesa di conferma…
                                            </p>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={cancelCliLogin}
                                                disabled={cancel.processing}
                                                title="Annulla il login in corso"
                                                className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                <X className="w-3.5 h-3.5 mr-1" aria-hidden />
                                                Annulla
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <RefreshCw className="w-3 h-3 animate-spin" aria-hidden />
                                        Avvio del login in corso…
                                    </p>
                                )}
                            </div>
                        )}

                        {timedOut && (
                            <p className="flex items-start gap-1.5 text-xs text-amber-500">
                                <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" aria-hidden />
                                <span>Codice scaduto o login non confermato. Clicca &laquo;Collega / Riconnetti&raquo; per riprovare.</span>
                            </p>
                        )}

                        {loginFlow.status === 'failed' && (
                            <p className="flex items-start gap-1.5 text-xs text-destructive">
                                <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5" aria-hidden />
                                <span>{loginFlow.error ?? 'Login non riuscito.'} Riprova, oppure da terminale: <code className="font-mono">docker exec -it wealth-tracker-app-1 sc login</code></span>
                            </p>
                        )}

                        {!inProgress && state.cli_logged_in === false && loginFlow.status !== 'failed' && (
                            <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                                <AlertTriangle className="w-3.5 h-3.5 shrink-0 mt-0.5 text-amber-500" aria-hidden />
                                <span>
                                    Clicca &laquo;Collega / Riconnetti&raquo;: comparirà un link da aprire nel browser e un codice da inserire (con 2FA). Poi la sincronizzazione automatica riprende da sola.
                                </span>
                            </p>
                        )}
                    </>
                )}

                <TransactionAssetsBlock assets={transactionAssets} />
            </CardContent>
        </Card>
    );
}
