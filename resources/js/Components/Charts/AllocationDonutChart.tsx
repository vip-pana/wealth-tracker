import {
    PieChart,
    Pie,
    Cell,
    Tooltip,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatCurrencyNoDecimals } from '@/lib/formatters';
import { Money } from '@/Components/ui/Money';
import { useValuesHidden } from '@/lib/privacy';
import type { AllocationSlice } from '@/types/analytics';

interface Props {
    data: AllocationSlice[];
    note?: string;
}

export default function AllocationDonutChart({ data, note }: Props) {
    const hidden = useValuesHidden();
    const total = data.reduce((s, d) => s + d.value, 0);
    const visible = data.filter((d) => d.value > 0);

    return (
        <Card>
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Composizione attuale</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3">
                <div className="donut-container">
                    <div className="donut-layout flex flex-col items-center gap-4">
                    {/* Donut */}
                    <div className="flex-shrink-0">
                        <PieChart width={170} height={170}>
                            <Pie
                                data={visible}
                                cx={85}
                                cy={85}
                                innerRadius={52}
                                outerRadius={76}
                                paddingAngle={3}
                                dataKey="value"
                                nameKey="name"
                                startAngle={90}
                                endAngle={-270}
                            >
                                {visible.map((entry) => (
                                    <Cell key={entry.name} fill={entry.color} stroke="hsl(var(--card))" strokeWidth={2} tabIndex={-1} style={{ outline: 'none' }} />
                                ))}
                            </Pie>
                            {!hidden && (
                            <Tooltip
                                formatter={(v, name) => [
                                    `${formatCurrencyNoDecimals((v as number) ?? 0)} (${total > 0 ? ((((v as number) ?? 0) / total) * 100).toFixed(1) : 0}%)`,
                                    name,
                                ]}
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
                        </PieChart>
                    </div>

                    {/* Legend table */}
                    <table className="donut-legend w-full text-xs">
                        <thead>
                            <tr className="text-muted-foreground">
                                <th className="text-left pb-1.5 font-medium">Categoria</th>
                                <th className="text-right pb-1.5 font-medium">Valore</th>
                                <th className="text-right pb-1.5 font-medium pl-2">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((entry) => (
                                <tr key={entry.name}>
                                    <td className="py-1 pr-2">
                                        <div className="flex items-center gap-1.5">
                                            <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: entry.color }} />
                                            <span className="text-muted-foreground">{entry.name}</span>
                                        </div>
                                    </td>
                                    <td className="py-1 text-right font-mono font-medium whitespace-nowrap">
                                        <Money value={entry.value} variant="no-decimals" />
                                    </td>
                                    <td className="py-1 pl-2 text-right font-mono text-muted-foreground whitespace-nowrap">
                                        {total > 0 ? ((entry.value / total) * 100).toFixed(1) : '0.0'}%
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
