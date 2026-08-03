import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import TransactionsDialog from '@/Components/Data/TransactionsDialog';
import { Money } from '@/Components/ui/Money';
import { formatPercent } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import { ReceiptText } from 'lucide-react';
import type { PositionReturns, PositionReturn } from '@/types/analytics';

function pnlClass(value: number | null): string {
    if (value == null) return '';
    return value >= 0 ? 'text-green-500' : 'text-red-500';
}

function SummaryStat({ label, value, tone }: { label: string; value: React.ReactNode; tone?: string }) {
    return (
        <div>
            <p className="text-xs text-muted-foreground mb-0.5">{label}</p>
            <p className={cn('text-base font-bold', tone)}>{value}</p>
        </div>
    );
}

/**
 * Share positions driven by imported broker transactions: cost basis, true
 * return and per-position transaction history.
 *
 * Unlike the rest of the page these figures span the whole history and are
 * deduplicated by ISIN, so they don't follow the selected month — the card says
 * so. Renders nothing when no asset is transaction-managed.
 */
export default function PositionsCard({ returns }: { returns: PositionReturns | null }) {
    const [txAsset, setTxAsset] = useState<{ id: number; name: string } | null>(null);

    if (returns === null) {
        return null;
    }

    return (
        <>
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base">Posizioni a quote</CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Rendimento reale su tutto lo storico: non segue il mese selezionato. Apri una posizione per lo storico transazioni.
                    </p>
                </CardHeader>
                <CardContent className="p-4 pt-0">
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <SummaryStat label="Versato" value={<Money value={returns.aggregate.cost_basis} />} />
                        <SummaryStat label="Valore attuale" value={<Money value={returns.aggregate.current_value} />} />
                        <SummaryStat
                            label="Rendimento"
                            tone={pnlClass(returns.aggregate.unrealised_pnl)}
                            value={
                                <>
                                    <Money value={returns.aggregate.unrealised_pnl} />
                                    {returns.aggregate.unrealised_pnl_pct !== null && (
                                        <span className="text-sm font-medium"> ({formatPercent(returns.aggregate.unrealised_pnl_pct)})</span>
                                    )}
                                </>
                            }
                        />
                        {returns.aggregate.realised_pnl !== 0 && (
                            <SummaryStat
                                label="Realizzato"
                                tone={pnlClass(returns.aggregate.realised_pnl)}
                                value={<Money value={returns.aggregate.realised_pnl} />}
                            />
                        )}
                    </div>
                </CardContent>

                <CardContent className="p-0 border-t border-border">
                    {/* Desktop */}
                    <div className="hidden lg:block">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Posizione</TableHead>
                                    <TableHead className="text-right">Quote</TableHead>
                                    <TableHead className="text-right">Prezzo medio</TableHead>
                                    <TableHead className="text-right">Versato</TableHead>
                                    <TableHead className="text-right">Valore</TableHead>
                                    <TableHead className="text-right">Rendimento</TableHead>
                                    <TableHead className="w-12" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {returns.positions.map((p: PositionReturn) => (
                                    <TableRow
                                        key={p.id}
                                        className="cursor-pointer"
                                        onClick={() => setTxAsset({ id: p.id, name: p.name })}
                                    >
                                        <TableCell className="font-medium">{p.name}</TableCell>
                                        <TableCell className="text-right font-mono text-sm">{p.shares}</TableCell>
                                        <TableCell className="text-right font-mono"><Money value={p.average_cost} /></TableCell>
                                        <TableCell className="text-right font-mono"><Money value={p.cost_basis} /></TableCell>
                                        <TableCell className="text-right font-mono">
                                            {p.current_value !== null ? <Money value={p.current_value} /> : '—'}
                                        </TableCell>
                                        <TableCell className={cn('text-right font-mono', pnlClass(p.unrealised_pnl))}>
                                            {p.unrealised_pnl !== null ? (
                                                <>
                                                    <Money value={p.unrealised_pnl} />
                                                    {p.unrealised_pnl_pct !== null && (
                                                        <span className="text-xs"> ({formatPercent(p.unrealised_pnl_pct)})</span>
                                                    )}
                                                </>
                                            ) : '—'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <ReceiptText className="w-4 h-4 text-muted-foreground inline" />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Mobile */}
                    <div className="lg:hidden divide-y divide-border">
                        {returns.positions.map((p: PositionReturn) => (
                            <button
                                key={p.id}
                                className="w-full text-left p-4 space-y-1.5"
                                onClick={() => setTxAsset({ id: p.id, name: p.name })}
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <span className="font-medium">{p.name}</span>
                                    <span className={cn('font-mono text-sm', pnlClass(p.unrealised_pnl))}>
                                        {p.unrealised_pnl !== null ? (
                                            <>
                                                <Money value={p.unrealised_pnl} />
                                                {p.unrealised_pnl_pct !== null && <span className="text-xs"> ({formatPercent(p.unrealised_pnl_pct)})</span>}
                                            </>
                                        ) : '—'}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    {p.shares} quote · prezzo medio <Money value={p.average_cost} /> · versato <Money value={p.cost_basis} />
                                </p>
                            </button>
                        ))}
                    </div>
                </CardContent>
            </Card>

            <TransactionsDialog asset={txAsset} onClose={() => setTxAsset(null)} />
        </>
    );
}
