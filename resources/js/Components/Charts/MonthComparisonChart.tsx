import {
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { ChartEmptyState } from '@/Components/Charts/ChartEmptyState';
import { formatCurrencyCompact, formatCurrency, formatDateLabel, truncateLabel } from '@/lib/formatters';
import { useValuesHidden, MASKED_TICK } from '@/lib/privacy';
import { useIsMobile } from '@/lib/media';
import type { MonthComparisonPoint } from '@/types/analytics';

interface Props {
    data: MonthComparisonPoint[];
    months: [string, string] | null; // [prev, current] as YYYY-MM-DD
    title?: string;
    note?: string;
}

export default function MonthComparisonChart({ data, months, title = 'Confronto tra snapshot', note }: Props) {
    const hidden = useValuesHidden();
    const isMobile = useIsMobile();
    const [prevDate, currDate] = months ?? ['Prec.', 'Attuale'];

    const prevLabel = prevDate.length > 7 ? formatDateLabel(prevDate) : prevDate;
    const currLabel = currDate.length > 7 ? formatDateLabel(currDate) : currDate;

    const visibleData = data.filter((d) => d.current > 0 || d.previous > 0);

    return (
        <Card className="flex flex-col h-64 lg:h-full overflow-hidden">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">{title}</CardTitle>
                {note && <p className="text-[11px] text-muted-foreground">{note}</p>}
            </CardHeader>
            <CardContent className="px-3 pb-3 flex flex-col flex-1 min-h-0">
                {!months || visibleData.length === 0 ? (
                    <ChartEmptyState message="Servono almeno due snapshot per confrontarli." />
                ) : (
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={visibleData} margin={{ top: 5, right: 10, left: isMobile ? 0 : 10, bottom: 5 }}>
                        <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                        {/* Category names are user-defined and unbounded. interval={0}
                            keeps every bar labelled rather than letting Recharts drop
                            some silently; truncation keeps those labels legible. */}
                        <XAxis
                            dataKey="category"
                            tick={{ fontSize: 11 }}
                            interval={isMobile ? 0 : 'preserveEnd'}
                            tickFormatter={(v) => isMobile ? truncateLabel(String(v), 6) : String(v)}
                        />
                        <YAxis
                            tickFormatter={hidden ? () => MASKED_TICK : formatCurrencyCompact}
                            tick={{ fontSize: 11 }}
                            width={isMobile ? 40 : 70}
                        />
                        {!hidden && (
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
                        )}
                        <Bar dataKey="previous" name={prevLabel} fill="hsl(var(--muted-foreground))" radius={[4, 4, 0, 0]} opacity={0.7} />
                        <Bar dataKey="current" name={currLabel} fill="hsl(var(--primary))" radius={[4, 4, 0, 0]} />
                    </BarChart>
                </ResponsiveContainer>
                )}
                {months && visibleData.length > 0 && (
                    <div className="flex shrink-0 justify-center gap-4 mt-1 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span className="inline-block w-3 h-3 rounded-sm" style={{ backgroundColor: 'hsl(var(--muted-foreground))', opacity: 0.7 }} />
                            {prevLabel}
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="inline-block w-3 h-3 rounded-sm" style={{ backgroundColor: 'hsl(var(--primary))' }} />
                            {currLabel}
                        </span>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}
