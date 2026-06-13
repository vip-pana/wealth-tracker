import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatPercent, formatDateLong } from '@/lib/formatters';
import { Money } from '@/Components/ui/Money';
import { Lightbulb, PieChart, Coins, Activity, Target } from 'lucide-react';
import type { PortfolioMetrics } from '@/types/analytics';

function Row({
    icon: Icon,
    label,
    value,
    hint,
}: {
    icon: typeof PieChart;
    label: string;
    value: React.ReactNode;
    hint?: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <Icon className="w-4 h-4 text-muted-foreground flex-shrink-0 mt-0.5" />
            <div className="min-w-0">
                <p className="text-sm">
                    <span className="text-muted-foreground">{label}: </span>
                    <span className="font-medium">{value}</span>
                </p>
                {hint && <p className="text-xs text-muted-foreground mt-0.5">{hint}</p>}
            </div>
        </div>
    );
}

export default function PortfolioInsights({ metrics }: { metrics: PortfolioMetrics }) {
    if (!metrics.hasData) {
        return null;
    }

    const { concentration, liquidity, volatility, goalEta, allocationDrift } = metrics;

    // The single category that has drifted the most, in either direction.
    const topDrift = allocationDrift[0];

    const goal = (() => {
        if (!goalEta) return null;
        if (goalEta.reached) return { line: 'Obiettivo già raggiunto.', hint: undefined as string | undefined };

        const lowConfidence = goalEta.low_confidence
            ? 'Stima poco affidabile: pochi mesi di dati.'
            : undefined;

        if (goalEta.projected_date) {
            const onTrack =
                goalEta.on_track == null
                    ? ''
                    : goalEta.on_track
                      ? ' — in linea con la data obiettivo'
                      : ' — oltre la data obiettivo';
            return {
                line: `Al ritmo attuale lo raggiungi intorno al ${formatDateLong(goalEta.projected_date)}${onTrack}.`,
                hint: lowConfidence,
            };
        }
        return {
            line: 'Al ritmo attuale non è in crescita verso l\'obiettivo.',
            hint: lowConfidence,
        };
    })();

    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-sm flex items-center gap-2">
                    <Lightbulb className="w-4 h-4 text-amber-500" />
                    Lettura del portafoglio
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
                <Row
                    icon={PieChart}
                    label="Concentrazione"
                    value={`${concentration.top_category} al ${concentration.top_share_pct.toFixed(1)}%`}
                    hint={
                        concentration.hhi >= 5000
                            ? 'Portafoglio molto concentrato su una sola voce.'
                            : concentration.hhi >= 3000
                              ? 'Concentrazione moderata.'
                              : 'Patrimonio ben distribuito.'
                    }
                />
                <Row
                    icon={Coins}
                    label="Liquidità ferma"
                    value={<><Money value={liquidity.value} /> ({liquidity.share_pct.toFixed(1)}%)</>}
                    hint={
                        liquidity.share_pct >= 30
                            ? 'Quota di liquidità alta: valuta se sta perdendo terreno sull\'inflazione.'
                            : undefined
                    }
                />
                {volatility.monthly_stddev_pct != null && (
                    <Row
                        icon={Activity}
                        label="Volatilità mensile"
                        value={`±${volatility.monthly_stddev_pct.toFixed(2)}%`}
                        hint={
                            volatility.best_month_pct != null && volatility.worst_month_pct != null
                                ? `Migliore ${formatPercent(volatility.best_month_pct)}, peggiore ${formatPercent(volatility.worst_month_pct)}.`
                                : undefined
                        }
                    />
                )}
                {topDrift && Math.abs(topDrift.delta_pp) >= 1 && (
                    <Row
                        icon={PieChart}
                        label="Spostamento maggiore"
                        value={`${topDrift.name} ${topDrift.delta_pp > 0 ? '+' : ''}${topDrift.delta_pp.toFixed(1)} punti`}
                        hint={`Da ${topDrift.share_pct_then.toFixed(1)}% a ${topDrift.share_pct.toFixed(1)}% del totale.`}
                    />
                )}
                {goal && <Row icon={Target} label="Obiettivo" value={goal.line} hint={goal.hint} />}
            </CardContent>
        </Card>
    );
}
