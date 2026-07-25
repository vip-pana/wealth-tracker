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
import type { StackedBarPoint } from '@/types/analytics';
import type { Category } from '@/types/models';

interface Props {
    data: StackedBarPoint[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    note?: string;
}

export default function StackedBarChart({ data, categories, note }: Props) {
    const hidden = useValuesHidden();
    const visibleCategories = categories.filter((cat) =>
        data.some((point) => (point[cat.name] as number ?? 0) > 0)
    );

    return (
        <Card>
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Composizione per categoria</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3">
                <ResponsiveContainer width="100%" height={200}>
                    <BarChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatDateLabel}
                            tick={{ fontSize: 11 }}
                        />
                        <YAxis
                            tickFormatter={hidden ? () => MASKED_TICK : formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={70}
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
                        />
                        )}
                        <Legend
                            iconType="circle"
                            iconSize={8}
                            wrapperStyle={{ fontSize: 11, paddingTop: 4 }}
                        />
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
