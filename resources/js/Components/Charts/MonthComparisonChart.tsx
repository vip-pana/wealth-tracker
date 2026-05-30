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
import type { MonthComparisonPoint } from '@/types/analytics';

interface Props {
    data: MonthComparisonPoint[];
    months: [string, string] | null; // [prev, current] as YYYY-MM-DD
}

export default function MonthComparisonChart({ data, months }: Props) {
    const [prevDate, currDate] = months ?? ['Prec.', 'Attuale'];

    const prevLabel = prevDate.length > 7 ? formatDateLabel(prevDate) : prevDate;
    const currLabel = currDate.length > 7 ? formatDateLabel(currDate) : currDate;

    const visibleData = data.filter((d) => d.current > 0 || d.previous > 0);

    return (
        <Card>
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Confronto tra snapshot</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3">
                <ResponsiveContainer width="100%" height={200}>
                    <BarChart data={visibleData} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis dataKey="category" tick={{ fontSize: 11 }} />
                        <YAxis
                            tickFormatter={formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={70}
                        />
                        <Tooltip
                            formatter={(v) => [formatCurrency((v as number) ?? 0)]}
                            contentStyle={{
                                fontSize: 12,
                                backgroundColor: 'hsl(var(--card))',
                                borderColor: 'hsl(var(--border))',
                                color: 'hsl(var(--card-foreground))',
                            }}
                            labelStyle={{ color: 'hsl(var(--card-foreground))' }}
                            itemStyle={{ color: 'hsl(var(--card-foreground))' }}
                        />
                        <Legend iconType="rect" />
                        <Bar dataKey="previous" name={prevLabel} fill="hsl(var(--muted-foreground))" radius={[4, 4, 0, 0]} opacity={0.7} />
                        <Bar dataKey="current" name={currLabel} fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
            </CardContent>
        </Card>
    );
}
