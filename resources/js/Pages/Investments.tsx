import { useState } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
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
import { Download, CandlestickChart, ReceiptText } from 'lucide-react';
import type { PositionReturns, PositionReturn } from '@/types/analytics';

interface Props {
    returns: PositionReturns | null;
}

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

export default function Investments({ returns }: Props) {
    const [txAsset, setTxAsset] = useState<{ id: number; name: string } | null>(null);

    return (
        <>
            <Head title="Investimenti" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={CandlestickChart}
                    title="Investimenti"
                    subtitle="Posizioni a quote, rendimento reale e storico transazioni"
                    actions={
                        <a href="/export/csv" download>
                            <Button variant="outline" size="sm">
                                <Download className="w-4 h-4 mr-2" />
                                Esporta CSV
                            </Button>
                        </a>
                    }
                />

                {returns === null ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground text-sm">
                            Nessuna posizione a quote. Importa le transazioni dal broker (Impostazioni → Scalable) per vedere qui rendimento e storico.
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        {/* Aggregate summary */}
                        <Card>
                            <CardContent className="p-4">
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
                        </Card>

                        {/* Positions table */}
                        <Card>
                            <CardContent className="p-0">
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
                    </>
                )}
            </div>

            <TransactionsDialog asset={txAsset} onClose={() => setTxAsset(null)} />
        </>
    );
}

Investments.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
