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
import type { ForecastPoint } from '@/types/analytics';

interface Props {
    data: ForecastPoint[];
}

export default function ForecastChart({ data }: Props) {
    // Find the split point between historical and forecast
    const splitIndex = data.findIndex((d) => d.forecast !== null && d.actual === null);
    const splitDate = splitIndex > 0 ? data[splitIndex]?.date : null;

    return (
        <Card>
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Previsioni (prossimi 6 mesi)</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3">
                {data.length === 0 ? (
                    <ChartEmptyState message="Servono almeno due snapshot per stimare una previsione." />
                ) : (
                <ResponsiveContainer width="100%" height={200}>
                    <ComposedChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatDateLabel}
                            tick={{ fontSize: 11 }}
                        />
                        <YAxis
                            tickFormatter={formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={70}
                        />
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
                        />
                        <Legend iconType="line" />
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
