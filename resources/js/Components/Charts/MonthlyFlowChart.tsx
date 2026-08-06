import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ReferenceLine,
    ResponsiveContainer,
    Cell,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ChartEmptyState } from '@/Components/Charts/ChartEmptyState';
import { formatCurrency, formatCurrencyCompact, formatMonthLabel, formatMonthLong } from '@/lib/formatters';
import { useValuesHidden, MASKED_TICK } from '@/lib/privacy';
import { useIsMobile } from '@/lib/media';
import type { MonthlyFlowPoint } from '@/types/analytics';

interface Props {
    data: MonthlyFlowPoint[];
    title?: string;
    note?: string;
}

/**
 * One bar per month: green when you saved that month, red when you eroded.
 * Unlike GrowthRateChart, which shows percentages, these are euros — so the
 * axis and the tooltip honour the privacy toggle.
 */
export default function MonthlyFlowChart({ data, title = 'Risparmio per mese', note }: Props) {
    const hidden = useValuesHidden();
    const isMobile = useIsMobile();

    return (
        <Card className="flex flex-col h-64 lg:h-full overflow-hidden">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">{title}</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3 flex-1 min-h-0">
                {data.length === 0 ? (
                    <ChartEmptyState message="Nessuna transazione registrata: collega un conto per vedere l'andamento." />
                ) : (
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 5, right: 10, left: isMobile ? 0 : 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            // "settembre 2025" is the longest tick in the app; on a
                            // phone it collides with itself, so drop to "set '25".
                            tickFormatter={(d) => isMobile ? formatMonthLabel(d as string) : formatMonthLong(d as string)}
                            tick={{ fontSize: 11 }}
                            minTickGap={isMobile ? 16 : 5}
                        />
                        <YAxis
                            tickFormatter={hidden ? () => MASKED_TICK : formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={isMobile ? 40 : 70}
                        />
                        {!hidden && (
                        <Tooltip
                            formatter={(v) => [formatCurrency((v as number) ?? 0), 'Risparmio']}
                            labelFormatter={(d) => formatMonthLong(d as string)}
                            contentStyle={{
                                fontSize: 12,
                                backgroundColor: 'hsl(var(--card))',
                                borderColor: 'hsl(var(--border))',
                                color: 'hsl(var(--card-foreground))',
                            }}
                            labelStyle={{ color: 'hsl(var(--card-foreground))' }}
                            itemStyle={{ color: 'hsl(var(--card-foreground))' }}
                        />
                        )}
                        <ReferenceLine y={0} stroke="hsl(var(--border))" />
                        <Bar dataKey="net" radius={[4, 4, 0, 0]}>
                            {data.map((entry) => (
                                <Cell
                                    key={entry.date}
                                    fill={entry.net >= 0 ? '#22c55e' : '#ef4444'}
                                />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
