import { useMemo, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatCurrency } from '@/lib/formatters';
import { requiredMonthlyContribution } from '@/lib/pacMath';
import { monthsUntil } from '@/lib/goalMath';
import type { GoalSimulatorWidget } from '@/Components/Advisor/types';

/**
 * Interactive goal simulator: drag the target amount and the target year and
 * watch the required monthly contribution update live, using the same annuity
 * maths (lib/pacMath.requiredMonthlyContribution) the PHP tool used. The
 * inverse of the PAC simulator — here the horizon is fixed and we solve for the
 * monthly payment. The return is a planning assumption, not a forecast.
 */
export function GoalSimulator({ data }: { data: GoalSimulatorWidget['data'] }) {
    const startYear = new Date().getFullYear();
    const initialYear = new Date(data.target_date + 'T00:00:00').getFullYear();

    const [target, setTarget] = useState(Math.round(data.target_value));
    const [year, setYear] = useState(Math.max(startYear + 1, initialYear));

    const required = useMemo(() => {
        // At the initial target/year, use the exact month count the backend
        // computed (data.months) so the figure matches the prose reply — the JS
        // monthsUntil() and PHP's diffInMonths() can differ by a month, which
        // shifted the result by a few euro. Recompute only once the user drags a
        // slider to a different target or year.
        const untouched = target === Math.round(data.target_value) && year === initialYear;
        const months = untouched ? data.months : monthsUntil(`${year}-12-31`);
        return requiredMonthlyContribution(data.current_net_worth, target, months, data.annual_return);
    }, [data.current_net_worth, data.annual_return, data.months, data.target_value, initialYear, target, year]);

    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Simulatore obiettivo</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label className="block text-xs">
                        <span className="text-muted-foreground">Obiettivo</span>
                        <span className="ml-1 font-medium text-foreground">{formatCurrency(target)}</span>
                        <input
                            type="range"
                            min={10000}
                            max={2000000}
                            step={10000}
                            value={target}
                            onChange={(e) => setTarget(Number(e.target.value))}
                            className="mt-1 w-full accent-primary"
                            aria-label="Importo obiettivo"
                        />
                    </label>
                    <label className="block text-xs">
                        <span className="text-muted-foreground">Entro il</span>
                        <span className="ml-1 font-medium text-foreground">{year}</span>
                        <input
                            type="range"
                            min={startYear + 1}
                            max={startYear + 40}
                            step={1}
                            value={year}
                            onChange={(e) => setYear(Number(e.target.value))}
                            className="mt-1 w-full accent-primary"
                            aria-label="Anno obiettivo"
                        />
                    </label>
                </div>

                <div className="text-sm">
                    {required <= 0 ? (
                        <span className="text-muted-foreground">
                            Con il patrimonio attuale e questo rendimento raggiungi già l’obiettivo entro la data: nessun
                            versamento aggiuntivo necessario.
                        </span>
                    ) : (
                        <span>
                            Versamento mensile necessario:{' '}
                            <span className="font-medium text-primary">{formatCurrency(required)}</span>
                        </span>
                    )}
                </div>

                <p className="text-xs text-muted-foreground">
                    Ipotesi di rendimento annuo {(data.annual_return * 100).toFixed(1)}% ({data.annual_return_source}):
                    assunzione di pianificazione, non una previsione di mercato.
                </p>
            </CardContent>
        </Card>
    );
}
