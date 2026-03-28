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
import { formatCurrencyCompact, formatCurrency, formatMonthLabel } from '@/lib/formatters';
import type { StackedBarPoint } from '@/types/analytics';
import type { Category } from '@/types/models';

interface Props {
    data: StackedBarPoint[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
}

export default function StackedBarChart({ data, categories }: Props) {
    return (
        <Card>
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Composizione per categoria</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3">
                <ResponsiveContainer width="100%" height={200}>
                    <BarChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
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
                            labelStyle={{ color: 'hsl(var(--card-foreground))' }}
                            itemStyle={{ color: 'hsl(var(--card-foreground))' }}
                        />
                        <Legend iconType="circle" iconSize={8} />
                        {categories.map((cat) => (
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
