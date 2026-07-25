import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Landmark, Unlink } from 'lucide-react';
import { bankFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import { ConnectBankDialog } from '@/Components/Settings/ConnectBankDialog';
import { ReconnectButton } from '@/Components/Settings/ReconnectButton';
import { LinkAccountSelect } from '@/Components/Settings/LinkAccountSelect';
import { maskIban } from '@/Components/Settings/types';
import type { BankConnectionEntry, BankOption, LinkableAsset } from '@/Components/Settings/types';

export function BankConnectionsCard({ connections, banks, assets, redirectReady }: { connections: BankConnectionEntry[]; banks: BankOption[]; assets: LinkableAsset[]; redirectReady: boolean }) {
    const [connectOpen, setConnectOpen] = useState(false);
    const [disconnecting, setDisconnecting] = useState<number | null>(null);

    const disconnect = (id: number, name: string) => {
        if (confirm(`Rimuovere il collegamento con ${name}?\n\nGli asset restano, ma vengono persi anche i collegamenti agli asset: dovrai rifarli a mano.\n\nSe vuoi solo rinnovare un consenso scaduto, usa "Riconnetti" invece di rimuovere — mantiene i collegamenti.`)) {
            setDisconnecting(id);
            router.delete(`/banking/connections/${id}`, { preserveScroll: true, onFinish: () => setDisconnecting(null) });
        }
    };

    const statusBadge = (c: BankConnectionEntry) => {
        const until = c.valid_until ? new Date(c.valid_until).toLocaleDateString('it-IT') : null;
        if (c.status === 'active') {
            return <span className="text-xs text-emerald-400">Attivo{until ? ` · scade ${until}` : ''}</span>;
        }
        if (c.status === 'pending') {
            return <span className="text-xs text-amber-500">In attesa di consenso</span>;
        }
        return <span className="text-xs text-destructive">Scaduto{until ? ` il ${until}` : ''}</span>;
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
                <div>
                    <CardTitle className="text-base">Conti bancari</CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Collega un conto via open banking (sola lettura) per sincronizzarne il saldo. I saldi si aggiornano con &laquo;Aggiorna ora&raquo; insieme ai prezzi.
                    </p>
                </div>
                <Button size="sm" variant="outline" onClick={() => setConnectOpen(true)} disabled={banks.length === 0}>
                    <Landmark className="w-4 h-4 mr-1" />
                    Collega conto
                </Button>
            </CardHeader>
            <CardContent className="p-0">
                {banks.length === 0 && (
                    <p className="px-4 py-3 text-xs text-amber-500">
                        Enable Banking non configurato (chiave/credenziali mancanti).
                    </p>
                )}
                {connections.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                        Nessun conto collegato.
                    </p>
                ) : (
                    <div className="divide-y divide-border">
                        {connections.map((c) => (
                            <div key={c.id} className="px-4 py-3 space-y-2">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">{c.aspsp_name}</span>
                                        {statusBadge(c)}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {c.status === 'expired' && <ReconnectButton connection={c} />}
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
                                            onClick={() => disconnect(c.id, c.aspsp_name)}
                                            disabled={disconnecting === c.id}
                                            title="Rimuovi collegamento"
                                        >
                                            <Unlink className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>
                                {c.accounts.map((acc) => (
                                    <div key={acc.id} className="flex items-center justify-between gap-3 pl-2">
                                        <div className="min-w-0">
                                            <span className="block text-xs font-mono text-foreground truncate">
                                                {maskIban(acc.iban) ?? acc.name ?? `Conto ${acc.id}`}
                                            </span>
                                            {acc.name && acc.iban && (
                                                <span className="block text-xs text-muted-foreground truncate">{acc.name}</span>
                                            )}
                                            {acc.linked_name && (
                                                acc.last_sync_status === 'failed' ? (
                                                    <span className="block text-xs text-destructive" title={acc.last_sync_error ?? undefined}>
                                                        Ultimo sync fallito
                                                    </span>
                                                ) : (() => {
                                                    const f = bankFreshness(acc.synced_at);
                                                    return (
                                                        <span className={cn('block text-xs', f.stale ? 'text-amber-500' : 'text-muted-foreground')}>
                                                            Saldo {f.label}
                                                        </span>
                                                    );
                                                })()
                                            )}
                                        </div>
                                        <LinkAccountSelect account={acc} assets={assets} />
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
            <ConnectBankDialog open={connectOpen} onClose={() => setConnectOpen(false)} banks={banks} redirectReady={redirectReady} />
        </Card>
    );
}
