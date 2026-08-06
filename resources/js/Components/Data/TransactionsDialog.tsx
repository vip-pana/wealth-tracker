import { useEffect, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/Components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDateLabel } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import { Money } from '@/Components/ui/Money';
import type { PositionSummary, TransactionRow } from '@/types/models';

// Only id (to fetch) and name (for the title) are needed, so any asset-like
// object works — an Asset row from the table or a row of the positions card.
interface AssetRef {
    id: number;
    name: string;
}

interface Props {
    asset: AssetRef | null;
    onClose: () => void;
}

interface Payload {
    transactions: TransactionRow[];
    position: PositionSummary;
}

function Stat({ label, value, tone }: { label: string; value: React.ReactNode; tone?: 'pos' | 'neg' }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground">{label}</p>
            <p className={cn('text-sm font-semibold font-mono', tone === 'pos' && 'text-green-500', tone === 'neg' && 'text-red-500')}>
                {value}
            </p>
        </div>
    );
}

export default function TransactionsDialog({ asset, onClose }: Props) {
    const [data, setData] = useState<Payload | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (asset === null) {
            return;
        }

        let cancelled = false;

        const load = async () => {
            setLoading(true);
            try {
                const r = await fetch(`/assets/${asset.id}/transactions`, { headers: { Accept: 'application/json' } });
                const payload: Payload = await r.json();
                if (!cancelled) setData(payload);
            } finally {
                if (!cancelled) setLoading(false);
            }
        };

        void load();

        return () => {
            cancelled = true;
        };
        // Re-fetch only when the asset identity changes, not on every new object ref.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [asset?.id]);

    const pos = data?.position;
    const pnlTone = pos?.unrealised_pnl == null ? undefined : pos.unrealised_pnl >= 0 ? 'pos' : 'neg';

    return (
        <Dialog open={asset !== null} onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Transazioni — {asset?.name}</DialogTitle>
                    <DialogDescription className="sr-only">Riepilogo posizione e storico delle transazioni di questo asset.</DialogDescription>
                </DialogHeader>

                {loading && <p className="text-sm text-muted-foreground py-4">Caricamento…</p>}

                {!loading && data && (
                    <>
                        {/* Position summary */}
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 rounded-md bg-muted/40 p-3">
                            <Stat label="Quote" value={String(pos!.shares)} />
                            <Stat label="Prezzo medio di carico" value={<Money value={pos!.average_cost} />} />
                            <Stat label="Costo totale" value={<Money value={pos!.cost_basis} />} />
                            {pos!.current_value !== null && (
                                <Stat label="Valore attuale" value={<Money value={pos!.current_value} />} />
                            )}
                            {pos!.unrealised_pnl !== null && (
                                <Stat
                                    label="Plus/minus latente"
                                    value={<><Money value={pos!.unrealised_pnl} />{pos!.unrealised_pnl_pct !== null ? ` (${pos!.unrealised_pnl_pct.toFixed(2)}%)` : ''}</>}
                                    tone={pnlTone}
                                />
                            )}
                            {pos!.realised_pnl !== 0 && (
                                <Stat
                                    label="Realizzato"
                                    value={<Money value={pos!.realised_pnl} />}
                                    tone={pos!.realised_pnl >= 0 ? 'pos' : 'neg'}
                                />
                            )}
                        </div>

                        {/* Transaction list. Four columns inside a max-w-lg dialog:
                            the default cell padding alone would take 128px of the
                            ~330px a phone has, so this instance goes compact — same
                            treatment as the goal deviation table. */}
                        {data.transactions.length === 0 ? (
                            <p className="text-sm text-muted-foreground py-4 text-center">
                                Nessuna transazione importata per questo asset.
                            </p>
                        ) : (
                            <div className="max-h-72 overflow-y-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="h-auto px-2 pb-2">Data</TableHead>
                                            <TableHead className="h-auto px-2 pb-2">Tipo</TableHead>
                                            <TableHead className="h-auto px-2 pb-2 text-right">Quote</TableHead>
                                            <TableHead className="h-auto px-2 pb-2 text-right">Prezzo</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.transactions.map((t) => (
                                            <TableRow key={t.id}>
                                                <TableCell className="px-2 py-2 text-sm whitespace-nowrap">{formatDateLabel(t.date)}</TableCell>
                                                <TableCell className="px-2 py-2">
                                                    <span className="inline-flex items-center gap-1.5">
                                                        <span className={cn(
                                                            'inline-flex whitespace-nowrap rounded-full px-1.5 py-0.5 text-xs font-medium',
                                                            t.type === 'buy' ? 'bg-green-500/10 text-green-500' : 'bg-red-500/10 text-red-500',
                                                        )}>
                                                            {t.type === 'buy' ? 'Acquisto' : 'Vendita'}
                                                        </span>
                                                        {t.source === 'savings_plan' && (
                                                            <span className="inline-flex rounded-full bg-blue-500/10 px-1.5 py-0.5 text-xs font-medium text-blue-400" title="Acquisto automatico del piano di accumulo">
                                                                PAC
                                                            </span>
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="px-2 py-2 text-right font-mono text-sm">{t.shares}</TableCell>
                                                <TableCell className="px-2 py-2 text-right font-mono text-sm whitespace-nowrap"><Money value={t.price_per_share} /></TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
