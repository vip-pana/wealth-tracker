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
import { formatDateLabel, formatPercent } from '@/lib/formatters';
import type { GrowthRatePoint } from '@/types/analytics';

interface Props {
    data: GrowthRatePoint[];
    title?: string;
}

export default function GrowthRateChart({ data, title = 'Variazione tra snapshot (%)' }: Props) {
    return (
        <Card className="flex flex-col h-full">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">{title}</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 flex-1 min-h-[200px]">
                {data.length === 0 ? (
                    <ChartEmptyState message="Servono almeno due snapshot per confrontare la variazione." />
                ) : (
                <ResponsiveContainer width="100%" height="100%" minHeight={200}>
                    <BarChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatDateLabel}
                            tick={{ fontSize: 11 }}
                        />
                        <YAxis
                            tickFormatter={(v) => `${v}%`}
                            tick={{ fontSize: 11 }}
                            width={50}
                        />
                        <Tooltip
                            formatter={(v) => [formatPercent((v as number) ?? 0), 'Variazione']}
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
                        <ReferenceLine y={0} stroke="hsl(var(--border))" />
                        <Bar dataKey="change_pct" radius={[4, 4, 0, 0]}>
                            {data.map((entry) => (
                                <Cell
                                    key={entry.date}
                                    fill={entry.change_pct >= 0 ? '#22c55e' : '#ef4444'}
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
