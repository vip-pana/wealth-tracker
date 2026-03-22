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
import { formatCurrencyCompact, formatCurrency, formatMonthLabel } from '@/lib/formatters';
import type { ForecastPoint } from '@/types/analytics';

interface Props {
    data: ForecastPoint[];
}

export default function ForecastChart({ data }: Props) {
    // Find the split point between historical and forecast
    const splitIndex = data.findIndex((d) => d.forecast !== null && d.actual === null);
    const splitMonth = splitIndex > 0 ? data[splitIndex]?.month : null;

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">Previsioni (prossimi 6 mesi)</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={260}>
                    <ComposedChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="month"
                            tickFormatter={formatMonthLabel}
                            tick={{ fontSize: 11 }}
                        />
                        <YAxis
                            tickFormatter={formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={70}
                        />
                        <Tooltip
                            formatter={(v, name) => [formatCurrency((v as number) ?? 0), name]}
                            labelFormatter={(d) => formatMonthLabel(d as string)}
                            contentStyle={{
                                fontSize: 12,
                                backgroundColor: 'hsl(var(--card))',
                                borderColor: 'hsl(var(--border))',
                                color: 'hsl(var(--card-foreground))',
                            }}
                        />
                        <Legend iconType="line" />
                        {splitMonth && (
                            <ReferenceLine
                                x={splitMonth}
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
            </CardContent>
        </Card>
    );
}
