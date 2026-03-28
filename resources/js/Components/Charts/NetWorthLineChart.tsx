import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    ReferenceLine,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatCurrency, formatCurrencyCompact, formatMonthLabel } from '@/lib/formatters';
import type { NetWorthPoint } from '@/types/analytics';

interface Props {
    data: NetWorthPoint[];
    goalTarget?: number | null;
    goalName?: string | null;
}

export default function NetWorthLineChart({ data, goalTarget, goalName }: Props) {
    return (
        <Card>
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Patrimonio nel tempo</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3">
                <ResponsiveContainer width="100%" height={200}>
                    <LineChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="month"
                            tickFormatter={formatMonthLabel}
                            tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                            stroke="hsl(var(--border))"
                        />
                        <YAxis
                            tickFormatter={formatCurrencyCompact}
                            tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                            stroke="hsl(var(--border))"
                            width={70}
                        />
                        <Tooltip
                            formatter={(v) => [formatCurrency((v as number) ?? 0), 'Totale']}
                            labelFormatter={(d) => formatMonthLabel(d as string)}
                            contentStyle={{
                                fontSize: 12,
                                backgroundColor: 'hsl(var(--card))',
                                borderColor: 'hsl(var(--border))',
                                color: 'hsl(var(--card-foreground))',
                            }}
                            labelStyle={{ color: 'hsl(var(--card-foreground))' }}
                            itemStyle={{ color: 'hsl(var(--card-foreground))' }}
                        />
                        {goalTarget != null && (
                            <ReferenceLine
                                y={goalTarget}
                                stroke="#f59e0b"
                                strokeDasharray="5 3"
                                strokeWidth={1.5}
                                label={{
                                    value: goalName ?? 'Obiettivo',
                                    position: 'insideTopRight',
                                    fontSize: 10,
                                    fill: '#f59e0b',
                                }}
                            />
                        )}
                        <Line
                            type="monotone"
                            dataKey="total_value"
                            stroke="hsl(var(--primary))"
                            strokeWidth={2}
                            dot={{ r: 3 }}
                            activeDot={{ r: 5 }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
