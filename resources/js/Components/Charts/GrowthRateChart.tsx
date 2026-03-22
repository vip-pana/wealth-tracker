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
import { formatMonthLabel, formatPercent } from '@/lib/formatters';
import type { GrowthRatePoint } from '@/types/analytics';

interface Props {
    data: GrowthRatePoint[];
}

export default function GrowthRateChart({ data }: Props) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">Variazione mese su mese (%)</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={260}>
                    <BarChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="month"
                            tickFormatter={formatMonthLabel}
                            tick={{ fontSize: 11 }}
                        />
                        <YAxis
                            tickFormatter={(v) => `${v}%`}
                            tick={{ fontSize: 11 }}
                            width={50}
                        />
                        <Tooltip
                            formatter={(v) => [formatPercent((v as number) ?? 0), 'Variazione MoM']}
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
                        <ReferenceLine y={0} stroke="hsl(var(--border))" />
                        <Bar dataKey="mom_pct" radius={[4, 4, 0, 0]}>
                            {data.map((entry) => (
                                <Cell
                                    key={entry.month}
                                    fill={entry.mom_pct >= 0 ? '#22c55e' : '#ef4444'}
                                />
                            ))}
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
