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
import { useIsMobile } from '@/lib/media';
import type { GrowthRatePoint } from '@/types/analytics';

interface Props {
    data: GrowthRatePoint[];
    title?: string;
    note?: string;
}

export default function GrowthRateChart({ data, title = 'Variazione tra snapshot (%)', note }: Props) {
    const isMobile = useIsMobile();

    return (
        <Card className="flex flex-col h-64 lg:h-full overflow-hidden">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">{title}</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3 flex-1 min-h-0">
                {data.length === 0 ? (
                    <ChartEmptyState message="Servono almeno due snapshot per confrontare la variazione." />
                ) : (
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 5, right: 10, left: isMobile ? 0 : 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatDateLabel}
                            tick={{ fontSize: 11 }}
                            minTickGap={isMobile ? 24 : 5}
                        />
                        <YAxis
                            tickFormatter={(v) => `${v}%`}
                            tick={{ fontSize: 11 }}
                            width={isMobile ? 32 : 50}
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
