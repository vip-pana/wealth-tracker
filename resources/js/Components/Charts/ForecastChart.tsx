import {
    ComposedChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
    ReferenceLine,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ChartEmptyState } from '@/Components/Charts/ChartEmptyState';
import { formatCurrencyCompact, formatCurrency, formatDateLabel } from '@/lib/formatters';
import { useValuesHidden, MASKED_TICK } from '@/lib/privacy';
import { useIsMobile } from '@/lib/media';
import { findForecastSplitDate } from '@/lib/metrics';
import type { ForecastPoint } from '@/types/analytics';

interface Props {
    data: ForecastPoint[];
    note?: string;
}

export default function ForecastChart({ data, note }: Props) {
    const hidden = useValuesHidden();
    const isMobile = useIsMobile();
    // Find the split point between historical and forecast
    const splitDate = findForecastSplitDate(data);

    return (
        <Card className="flex flex-col h-64 lg:h-full overflow-hidden">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Previsioni (prossimi 6 mesi)</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3 flex-1 min-h-0">
                {data.length === 0 ? (
                    <ChartEmptyState message="Servono almeno due snapshot per stimare una previsione." />
                ) : (
                <ResponsiveContainer width="100%" height="100%">
                    <ComposedChart data={data} margin={{ top: 5, right: 10, left: isMobile ? 0 : 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatDateLabel}
                            tick={{ fontSize: 11 }}
                            minTickGap={isMobile ? 24 : 5}
                        />
                        <YAxis
                            tickFormatter={hidden ? () => MASKED_TICK : formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={isMobile ? 40 : 70}
                        />
                        {!hidden && (
                        <Tooltip
                            formatter={(v, name) => [formatCurrency((v as number) ?? 0), name]}
                            labelFormatter={(d) => formatDateLabel(d as string)}
                            contentStyle={{
                                fontSize: 12,
                                backgroundColor: 'hsl(var(--card))',
                                borderColor: 'hsl(var(--border))',
                                color: 'hsl(var(--card-foreground))',
                            }}
                            labelStyle={{ color: 'hsl(var(--card-foreground))' }}
                            itemStyle={{ color: 'hsl(var(--card-foreground))' }}
                            wrapperStyle={{ maxWidth: 'calc(100vw - 3rem)' }}
                        />
                        )}
                        <Legend iconType="line" iconSize={8} wrapperStyle={{ fontSize: isMobile ? 10 : 12 }} />
                        {splitDate && (
                            <ReferenceLine
                                x={splitDate}
                                stroke="hsl(var(--border))"
                                strokeDasharray="4 4"
                            />
                        )}
                        <Line
                            type="monotone"
                            dataKey="actual"
                            name="Reale"
                            stroke="hsl(var(--primary))"
                            strokeWidth={2}
                            dot={{ r: 3 }}
                            connectNulls={false}
                        />
                        <Line
                            type="monotone"
                            dataKey="trend"
                            name="Trend"
                            stroke="#6366f1"
                            strokeWidth={1.5}
                            strokeDasharray="4 2"
                            dot={false}
                            connectNulls={false}
                        />
                        <Line
                            type="monotone"
                            dataKey="forecast"
                            name="Previsione"
                            stroke="#f59e0b"
                            strokeWidth={2}
                            strokeDasharray="6 2"
                            dot={{ r: 3 }}
                            connectNulls={false}
                        />
                    </ComposedChart>
                </ResponsiveContainer>
                )}
            </CardContent>
        </Card>
    );
}
