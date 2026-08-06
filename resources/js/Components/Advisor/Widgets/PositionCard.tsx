import { Card, CardContent } from '@/Components/ui/card';
import { Money } from '@/Components/ui/Money';
import { formatPercent } from '@/lib/formatters';
import type { PositionCardWidget } from '@/Components/Advisor/types';

/**
 * Static detail card for a single position. For a transaction-managed position
 * (ETF/crypto with imported buys) it shows shares, average cost, value and a
 * coloured P&L; for a plain category (Bitcoin/Oro/Liquidità) only the current
 * value and portfolio weight are known. All figures come from PHP.
 */
export function PositionCard({ data }: { data: PositionCardWidget['data'] }) {
    return (
        <Card className="card-cq mt-3">
            <CardContent className="px-4 py-3">
                <div className="text-sm font-medium">{data.name}</div>

                {data.managed ? (
                    <dl className="bubble-grid mt-2 grid gap-x-4 gap-y-1.5 text-xs">
                        <Row label="Quote" value={data.shares.toLocaleString('it-IT', { maximumFractionDigits: 6 })} />
                        <Row label="Prezzo medio" money={data.average_cost} />
                        <Row label="Investito" money={data.cost_basis} />
                        {data.current_value !== null && <Row label="Valore attuale" money={data.current_value} />}
                        {data.unrealised_pnl !== null && (
                            <div className="bubble-grid-full mt-1 border-t border-border pt-2">
                                <dt className="text-muted-foreground">Guadagno/perdita</dt>
                                <dd
                                    className={`font-mono font-medium ${
                                        data.unrealised_pnl >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'
                                    }`}
                                >
                                    <Money value={data.unrealised_pnl} />
                                    {data.unrealised_pnl_pct !== null && (
                                        <span className="ml-1">({formatPercent(data.unrealised_pnl_pct)})</span>
                                    )}
                                </dd>
                            </div>
                        )}
                    </dl>
                ) : (
                    <dl className="mt-2 space-y-1.5 text-xs">
                        <Row label="Valore attuale" money={data.current_value} />
                        <Row label="Peso nel portafoglio" value={`${data.share_pct.toFixed(1)}%`} />
                        <p className="pt-1 text-muted-foreground">
                            Voce non gestita da transazioni: nessun prezzo medio o rendimento reale.
                        </p>
                    </dl>
                )}
            </CardContent>
        </Card>
    );
}

function Row({ label, value, money }: { label: string; value?: string; money?: number }) {
    return (
        <div className="flex items-center justify-between gap-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-mono font-medium">{money !== undefined ? <Money value={money} /> : value}</dd>
        </div>
    );
}
