import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatCurrency, formatCurrencyCompact, formatMonthLabel } from '@/lib/formatters';
import type { NetWorthPoint } from '@/types/analytics';

interface Props {
    data: NetWorthPoint[];
}

export default function NetWorthLineChart({ data }: Props) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">Patrimonio nel tempo</CardTitle>
            </CardHeader>
            <CardContent>
                <ResponsiveContainer width="100%" height={260}>
                    <LineChart data={data} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        <XAxis
                            dataKey="month"
                            tickFormatter={formatMonthLabel}
                            tick={{ fontSize: 11 }}
                            className="fill-muted-foreground"
                        />
                        <YAxis
                            tickFormatter={formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={70}
                            className="fill-muted-foreground"
                        />
                        <Tooltip
                            formatter={(v) => [formatCurrency((v as number) ?? 0), 'Totale']}
                            labelFormatter={(d) => formatMonthLabel(d as string)}
                            contentStyle={{ fontSize: 12 }}
                        />
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
