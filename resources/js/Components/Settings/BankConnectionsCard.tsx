import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Landmark, Unlink } from 'lucide-react';
import { bankFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import { ConnectBankDialog } from '@/Components/Settings/ConnectBankDialog';
import { ConnectionRow } from '@/Components/Settings/ConnectionRow';
import type { RowTone } from '@/Components/Settings/ConnectionRow';
import { ReconnectButton } from '@/Components/Settings/ReconnectButton';
import { LinkAccountSelect } from '@/Components/Settings/LinkAccountSelect';
import { maskIban } from '@/Components/Settings/types';
import type { BankConnectionEntry, BankOption, LinkableAsset } from '@/Components/Settings/types';

// Collapsed summary across every connection: the worst status wins, because a
// single expired consent is the thing that needs attention.
function summarise(connections: BankConnectionEntry[], configured: boolean): { tone: RowTone; label: string } {
    if (!configured) {
        return { tone: 'warn', label: 'Enable Banking non configurato' };
    }
    if (connections.length === 0) {
        return { tone: 'idle', label: 'Nessun conto collegato' };
    }

    const expired = connections.filter((c) => c.status === 'expired');
    if (expired.length > 0) {
        return { tone: 'error', label: `${expired.length} consenso scaduto — riconnetti` };
    }

    const pending = connections.filter((c) => c.status === 'pending');
    if (pending.length > 0) {
        return { tone: 'warn', label: `${pending.length} in attesa di consenso` };
    }

    const stale = connections.some((c) =>
        c.accounts.some((a) => a.last_sync_status === 'failed' || bankFreshness(a.synced_at).stale),
    );
    if (stale) {
        return { tone: 'warn', label: 'Saldi non aggiornati' };
    }

    const accounts = connections.reduce((n, c) => n + c.accounts.length, 0);
    const names = connections.map((c) => c.aspsp_name).join(', ');
    return { tone: 'ok', label: `${names} · ${accounts} cont${accounts === 1 ? 'o' : 'i'} attiv${accounts === 1 ? 'o' : 'i'}` };
}

export function BankConnectionsCard({ connections, banks, assets, redirectReady }: { connections: BankConnectionEntry[]; banks: BankOption[]; assets: LinkableAsset[]; redirectReady: boolean }) {
    const [connectOpen, setConnectOpen] = useState(false);
    const [disconnecting, setDisconnecting] = useState<number | null>(null);
    const summary = summarise(connections, banks.length > 0);
    const expired = connections.find((c) => c.status === 'expired');

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
        <ConnectionRow
            icon={Landmark}
            title="Conti bancari"
            tone={summary.tone}
            status={summary.label}
            actions={
                <>
                    {expired && <ReconnectButton connection={expired} />}
                    <Button size="sm" variant="outline" onClick={() => setConnectOpen(true)} disabled={banks.length === 0}>
                        <Landmark className="w-4 h-4 mr-1" />
                        Collega conto
                    </Button>
                </>
            }
        >
            <p className="text-xs text-muted-foreground">
                Collega un conto via open banking (sola lettura) per sincronizzarne il saldo. I saldi si aggiornano con &laquo;Aggiorna ora&raquo; insieme ai prezzi.
            </p>
            {banks.length === 0 && (
                <p className="mt-2 text-xs text-amber-500">
                    Enable Banking non configurato (chiave/credenziali mancanti).
                </p>
            )}
            {connections.length > 0 && (
                <div className="mt-3 divide-y divide-border rounded-md border border-border">
                    {connections.map((c) => (
                        <div key={c.id} className="px-3 py-3 space-y-2">
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
            <ConnectBankDialog open={connectOpen} onClose={() => setConnectOpen(false)} banks={banks} redirectReady={redirectReady} />
        </ConnectionRow>
    );
}
