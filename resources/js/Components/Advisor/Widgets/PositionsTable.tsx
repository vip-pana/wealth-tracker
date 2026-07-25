import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Money } from '@/Components/ui/Money';
import { formatPercent } from '@/lib/formatters';
import type { PositionsTableWidget } from '@/Components/Advisor/types';

/**
 * All transaction-managed positions in one table: shares, average cost, value
 * and coloured P&L. The eye compares positions faster than prose, and each
 * sign stays correct because the numbers come from PHP.
 */
export function PositionsTable({ data }: { data: PositionsTableWidget['data'] }) {
    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Rendimenti per posizione</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3">
                <div className="overflow-x-auto">
                    <table className="w-full text-xs">
                        <thead>
                            <tr className="text-muted-foreground">
                                <th className="pb-1.5 text-left font-medium">Posizione</th>
                                <th className="pb-1.5 text-right font-medium">Valore</th>
                                <th className="pb-1.5 pl-2 text-right font-medium">G/P</th>
                            </tr>
                        </thead>
                        <tbody>
                            {data.rows.map((row) => (
                                <tr key={row.name} className="border-t border-border">
                                    <td className="py-1.5 pr-2">{row.name}</td>
                                    <td className="py-1.5 text-right font-mono whitespace-nowrap">
                                        {row.current_value !== null ? <Money value={row.current_value} variant="no-decimals" /> : '—'}
                                    </td>
                                    <td
                                        className={`py-1.5 pl-2 text-right font-mono whitespace-nowrap ${
                                            row.unrealised_pnl === null
                                                ? 'text-muted-foreground'
                                                : row.unrealised_pnl >= 0
                                                  ? 'text-emerald-600 dark:text-emerald-400'
                                                  : 'text-red-600 dark:text-red-400'
                                        }`}
                                    >
                                        {row.unrealised_pnl === null ? (
                                            '—'
                                        ) : (
                                            <>
                                                <Money value={row.unrealised_pnl} variant="no-decimals" />
                                                {row.unrealised_pnl_pct !== null && (
                                                    <span className="ml-1">({formatPercent(row.unrealised_pnl_pct)})</span>
                                                )}
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    );
}
