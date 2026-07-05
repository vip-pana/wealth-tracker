import { useMemo, useState } from 'react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ReferenceLine,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatCurrency, formatCurrencyCompact } from '@/lib/formatters';
import { projectPac } from '@/lib/pacMath';
import { useValuesHidden, MASKED_TICK } from '@/lib/privacy';
import type { PacSimulatorWidget } from '@/Components/Advisor/types';

/**
 * Interactive PAC simulator. The advisor emits it when the user asks about the
 * effect of a monthly contribution; the user then drags the monthly amount and
 * the expected annual return and watches the projected time-to-goal recompute
 * live, using the same compound maths (lib/pacMath) the PHP tool used for its
 * prose. The return is a planning assumption, not a forecast — labelled as such.
 */
export function PacSimulator({ data }: { data: PacSimulatorWidget['data'] }) {
    const hidden = useValuesHidden();
    const [monthly, setMonthly] = useState(Math.round(data.monthly_amount));
    const [annualPct, setAnnualPct] = useState(Math.round(data.annual_return * 100));

    const { months, chart } = useMemo(() => {
        const projection = projectPac(data.current_net_worth, data.target_value, monthly, annualPct / 100);
        // Sample the balance curve yearly so the area chart stays readable even
        // over long horizons; always include the final point.
        const step = Math.max(1, Math.floor(projection.balances.length / 60));
        const chart = projection.balances
            .filter((_, i) => i % step === 0 || i === projection.balances.length - 1)
            .map((value, i, arr) => ({
                month: i === arr.length - 1 ? projection.balances.length - 1 : i * step,
                value,
            }));
        return { months: projection.months, chart };
    }, [data.current_net_worth, data.target_value, monthly, annualPct]);

    const years = months === null ? null : months / 12;

    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Simulatore PAC</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label className="block text-xs">
                        <span className="text-muted-foreground">Versamento mensile</span>
                        <span className="ml-1 font-medium text-foreground">{formatCurrency(monthly)}</span>
                        <input
                            type="range"
                            min={50}
                            max={3000}
                            step={50}
                            value={monthly}
                            onChange={(e) => setMonthly(Number(e.target.value))}
                            className="mt-1 w-full accent-primary"
                            aria-label="Versamento mensile"
                        />
                    </label>
                    <label className="block text-xs">
                        <span className="text-muted-foreground">Rendimento annuo ipotizzato</span>
                        <span className="ml-1 font-medium text-foreground">{annualPct}%</span>
                        <input
                            type="range"
                            min={0}
                            max={12}
                            step={1}
                            value={annualPct}
                            onChange={(e) => setAnnualPct(Number(e.target.value))}
                            className="mt-1 w-full accent-primary"
                            aria-label="Rendimento annuo ipotizzato"
                        />
                    </label>
                </div>

                <div className="text-sm">
                    {years === null ? (
                        <span className="text-muted-foreground">
                            Con questi valori l’obiettivo non viene raggiunto entro un orizzonte ragionevole (oltre 100 anni).
                        </span>
                    ) : (
                        <span>
                            Raggiungi l’obiettivo di{' '}
                            <span className="font-medium">{formatCurrency(data.target_value)}</span> in circa{' '}
                            <span className="font-medium text-primary">{years.toFixed(1)} anni</span>
                            {months !== null && <span className="text-muted-foreground"> ({months} mesi)</span>}.
                        </span>
                    )}
                </div>

                <div className="h-[160px]">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={chart} margin={{ top: 5, right: 10, left: 10, bottom: 5 }}>
                            <defs>
                                <linearGradient id="pacFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="hsl(var(--primary))" stopOpacity={0.3} />
                                    <stop offset="100%" stopColor="hsl(var(--primary))" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" className="stroke-border" />
                            <XAxis
                                dataKey="month"
                                tickFormatter={(m) => `${Math.round((m as number) / 12)}a`}
                                tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                                stroke="hsl(var(--border))"
                            />
                            <YAxis
                                tickFormatter={hidden ? () => MASKED_TICK : formatCurrencyCompact}
                                tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                                stroke="hsl(var(--border))"
                                width={60}
                            />
                            {!hidden && (
                                <Tooltip
                                    formatter={(v) => [formatCurrency((v as number) ?? 0), 'Patrimonio']}
                                    labelFormatter={(m) => `Anno ${Math.round((m as number) / 12)}`}
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
                            <ReferenceLine
                                y={data.target_value}
                                stroke="#f59e0b"
                                strokeDasharray="5 3"
                                strokeWidth={1.5}
                                label={{ value: 'Obiettivo', position: 'insideTopRight', fontSize: 10, fill: '#f59e0b' }}
                            />
                            <Area
                                type="monotone"
                                dataKey="value"
                                stroke="hsl(var(--primary))"
                                strokeWidth={2}
                                fill="url(#pacFill)"
                            />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>

                <p className="text-xs text-muted-foreground">
                    Il rendimento è un’ipotesi di pianificazione ({data.annual_return_source}), non una previsione di
                    mercato.
                    {data.low_confidence && ' Pochi mesi di dati tracciati: trattala come ordine di grandezza.'}
                </p>
            </CardContent>
        </Card>
    );
}
