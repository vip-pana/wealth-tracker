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
import { formatCurrencyCompact, formatCurrency } from '@/lib/formatters';
import type { MonthComparisonPoint } from '@/types/analytics';

interface Props {
    data: MonthComparisonPoint[];
    months: [string, string] | null; // [prev, current] as YYYY-MM-DD
}

export default function MonthComparisonChart({ data, months }: Props) {
    const [prevMonth, currMonth] = months ?? ['Mese prec.', 'Mese att.'];

    const prevLabel = prevMonth.length > 7
        ? new Date(prevMonth + 'T00:00:00').toLocaleDateString('it-IT', { month: 'short', year: '2-digit' })
        : prevMonth;
    const currLabel = currMonth.length > 7
        ? new Date(currMonth + 'T00:00:00').toLocaleDateString('it-IT', { month: 'short', year: '2-digit' })
        : currMonth;

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">Confronto mese su mese</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={260}>
                    <BarChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
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
