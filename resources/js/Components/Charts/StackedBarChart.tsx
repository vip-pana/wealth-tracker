import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatCurrencyCompact, formatCurrency, formatDateLabel } from '@/lib/formatters';
import { useValuesHidden, MASKED_TICK } from '@/lib/privacy';
import { useIsMobile } from '@/lib/media';
import type { StackedBarPoint } from '@/types/analytics';
import type { Category } from '@/types/models';

interface Props {
    data: StackedBarPoint[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    note?: string;
}

export default function StackedBarChart({ data, categories, note }: Props) {
    const hidden = useValuesHidden();
    const isMobile = useIsMobile();
    const visibleCategories = categories.filter((cat) =>
        data.some((point) => (point[cat.name] as number ?? 0) > 0)
    );

    return (
        <Card className="flex flex-col h-64 lg:h-full overflow-hidden">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Composizione per categoria</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3 flex-1 min-h-0">
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
                        {/* One entry per category, so on a phone this wraps to
                            several rows and leaves the bars almost no height.
                            The tooltip still names each colour. */}
                        {!isMobile && (
                        <Legend
                            iconType="circle"
                            iconSize={8}
                            wrapperStyle={{ fontSize: 11, paddingTop: 4 }}
                        />
                        )}
                        {visibleCategories.map((cat) => (
                            <Bar
                                key={cat.name}
                                dataKey={cat.name}
                                stackId="a"
                                fill={cat.color}
                                radius={0}
                            />
                        ))}
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
