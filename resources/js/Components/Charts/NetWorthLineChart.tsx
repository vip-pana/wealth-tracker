import {
    LineChart,
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
import { formatCurrency, formatCurrencyCompact, formatDateLabel, truncateLabel } from '@/lib/formatters';
import { useValuesHidden, MASKED_TICK } from '@/lib/privacy';
import { useIsMobile } from '@/lib/media';
import type { NetWorthPoint } from '@/types/analytics';

interface Props {
    data: NetWorthPoint[];
    goalTarget?: number | null;
    goalName?: string | null;
}

export default function NetWorthLineChart({ data, goalTarget, goalName }: Props) {
    const hidden = useValuesHidden();
    const isMobile = useIsMobile();

    // Only show the extra layer when it actually diverges from the total
    // somewhere in the series — otherwise (no buffer) both lines overlap and
    // the legend is just noise.
    const layered = data.some((p) => p.investable !== undefined && p.investable !== p.total_value);
    return (
        <Card className="flex flex-col h-64 lg:h-full overflow-hidden">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Patrimonio nel tempo</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 flex-1 min-h-0">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart data={data} margin={{ top: 5, right: 10, left: isMobile ? 0 : 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatDateLabel}
                            tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                            stroke="hsl(var(--border))"
                            minTickGap={isMobile ? 24 : 5}
                        />
                        <YAxis
                            tickFormatter={hidden ? () => MASKED_TICK : formatCurrencyCompact}
                            tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                            stroke="hsl(var(--border))"
                            width={isMobile ? 40 : 70}
                        />
                        {!hidden && (
                        <Tooltip
                            formatter={(v, name) => [formatCurrency((v as number) ?? 0), name as string]}
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
                        )}
                        {layered && <Legend wrapperStyle={{ fontSize: 11 }} />}
                        {goalTarget != null && (
                            <ReferenceLine
                                y={goalTarget}
                                stroke="#f59e0b"
                                strokeDasharray="5 3"
                                strokeWidth={1.5}
                                label={{
                                    value: isMobile
                                        ? truncateLabel(goalName ?? 'Obiettivo', 12)
                                        : goalName ?? 'Obiettivo',
                                    // The goal name is user-defined, so on a phone
                                    // it runs off the right edge of a ~230px plot.
                                    position: isMobile ? 'insideTopLeft' : 'insideTopRight',
                                    fontSize: isMobile ? 9 : 10,
                                    fill: '#f59e0b',
                                }}
                            />
                        )}
                        <Line
                            type="monotone"
                            dataKey="total_value"
                            name="Totale"
                            stroke="hsl(var(--primary))"
                            strokeWidth={2}
                            dot={{ r: 3 }}
                            activeDot={{ r: 5 }}
                        />
                        {layered && (
                            <Line
                                type="monotone"
                                dataKey="investable"
                                name="Investibile"
                                stroke="#06b6d4"
                                strokeWidth={1.5}
                                strokeDasharray="2 2"
                                dot={false}
                                activeDot={{ r: 4 }}
                            />
                        )}
                    </LineChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
