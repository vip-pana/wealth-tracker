import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatPercent, formatDateLong } from '@/lib/formatters';
import { Money } from '@/Components/ui/Money';
import { Lightbulb, PieChart, Coins, Activity, Target, TrendingUp, ChevronLeft, ChevronRight, Pause, Play } from 'lucide-react';
import type { PortfolioMetrics, PositionReturns } from '@/types/analytics';

/** How long each insight stays on screen before the next one slides in. */
export const ROTATE_MS = 8000;

export interface Insight {
    key: string;
    icon: typeof PieChart;
    label: string;
    value: React.ReactNode;
    hint?: React.ReactNode;
}

/**
 * The insights worth showing, in priority order. Pure so it can be tested
 * without driving the carousel: each entry is skipped when its data is missing,
 * so the caller gets only the rows it can actually render.
 */
export function buildInsights(metrics: PortfolioMetrics, positionReturns: PositionReturns | null): Insight[] {
    if (!metrics.hasData) {
        return [];
    }

    const { concentration, liquidity, volatility, goalEta, allocationDrift } = metrics;
    const insights: Insight[] = [];

    if (positionReturns) {
        const agg = positionReturns.aggregate;
        insights.push({
            key: 'returns',
            icon: TrendingUp,
            label: 'Rendimento investimenti',
            value: (
                <>
                    <Money value={agg.unrealised_pnl} />
                    {agg.unrealised_pnl_pct !== null && <> ({formatPercent(agg.unrealised_pnl_pct)})</>}
                </>
            ),
            hint: (
                <>
                    Versato <Money value={agg.cost_basis} />, ora vale <Money value={agg.current_value} />
                    {agg.realised_pnl !== 0 && (
                        <> · realizzato <Money value={agg.realised_pnl} /></>
                    )}
                </>
            ),
        });
    }

    insights.push({
        key: 'concentration',
        icon: PieChart,
        label: 'Concentrazione',
        value: `${concentration.top_category} al ${concentration.top_share_pct.toFixed(1)}%`,
        hint:
            concentration.hhi >= 5000
                ? 'Portafoglio molto concentrato su una sola voce.'
                : concentration.hhi >= 3000
                  ? 'Concentrazione moderata.'
                  : 'Patrimonio ben distribuito.',
    });

    insights.push({
        key: 'liquidity',
        icon: Coins,
        label: 'Liquidità ferma',
        value: <><Money value={liquidity.value} /> ({liquidity.share_pct.toFixed(1)}%)</>,
        hint:
            liquidity.share_pct >= 30
                ? 'Quota di liquidità alta: valuta se sta perdendo terreno sull\'inflazione.'
                : undefined,
    });

    if (volatility.monthly_stddev_pct != null) {
        insights.push({
            key: 'volatility',
            icon: Activity,
            label: 'Volatilità mensile',
            value: `±${volatility.monthly_stddev_pct.toFixed(2)}%`,
            hint:
                volatility.best_month_pct != null && volatility.worst_month_pct != null
                    ? `Migliore ${formatPercent(volatility.best_month_pct)}, peggiore ${formatPercent(volatility.worst_month_pct)}.`
                    : undefined,
        });
    }

    // The single category that has drifted the most, in either direction.
    const topDrift = allocationDrift[0];
    if (topDrift && Math.abs(topDrift.delta_pp) >= 1) {
        insights.push({
            key: 'drift',
            icon: PieChart,
            label: 'Spostamento maggiore',
            value: `${topDrift.name} ${topDrift.delta_pp > 0 ? '+' : ''}${topDrift.delta_pp.toFixed(1)} punti`,
            hint: `Da ${topDrift.share_pct_then.toFixed(1)}% a ${topDrift.share_pct.toFixed(1)}% del totale.`,
        });
    }

    if (goalEta) {
        const lowConfidence = goalEta.low_confidence
            ? 'Stima poco affidabile: pochi mesi di dati.'
            : undefined;

        if (goalEta.reached) {
            insights.push({ key: 'goal', icon: Target, label: 'Obiettivo', value: 'Obiettivo già raggiunto.' });
        } else if (goalEta.projected_date) {
            const onTrack =
                goalEta.on_track == null
                    ? ''
                    : goalEta.on_track
                      ? ' — in linea con la data obiettivo'
                      : ' — oltre la data obiettivo';
            insights.push({
                key: 'goal',
                icon: Target,
                label: 'Obiettivo',
                value: `Al ritmo attuale lo raggiungi intorno al ${formatDateLong(goalEta.projected_date)}${onTrack}.`,
                hint: lowConfidence,
            });
        } else {
            insights.push({
                key: 'goal',
                icon: Target,
                label: 'Obiettivo',
                value: 'Al ritmo attuale non è in crescita verso l\'obiettivo.',
                hint: lowConfidence,
            });
        }
    }

    return insights;
}

/**
 * One insight at a time, auto-advancing every ROTATE_MS. Arrows step through
 * them by hand; stepping by hand also pauses the rotation, so reading one
 * doesn't get interrupted mid-sentence. The card keeps a fixed minimum height
 * so the page below it doesn't jump as insights of different length cycle.
 */
export default function PortfolioInsights({ metrics, positionReturns }: { metrics: PortfolioMetrics; positionReturns: PositionReturns | null }) {
    const insights = buildInsights(metrics, positionReturns);
    const total = insights.length;

    const [idx, setIdx] = useState(0);
    const [paused, setPaused] = useState(false);

    useEffect(() => {
        if (paused || total < 2) return;
        const t = setInterval(() => setIdx((i) => (i + 1) % total), ROTATE_MS);
        return () => clearInterval(t);
    }, [paused, total]);

    if (total === 0) {
        return null;
    }

    // A hand-driven step pauses the rotation: the reader is in control now.
    function step(direction: -1 | 1) {
        setPaused(true);
        setIdx((i) => (i + direction + total) % total);
    }

    const current = insights[Math.min(idx, total - 1)];
    const Icon = current.icon;

    return (
        <Card className="h-full flex flex-col">
            <CardHeader className="p-3 pb-1.5 flex flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle className="text-xs font-medium text-muted-foreground flex items-center gap-1.5 min-w-0">
                    <Lightbulb className="w-3.5 h-3.5 text-amber-500 shrink-0" />
                    <span className="truncate">Lettura del portafoglio</span>
                </CardTitle>
                {total > 1 && (
                    <div className="flex items-center gap-0.5 shrink-0">
                        <button
                            type="button"
                            onClick={() => setPaused((p) => !p)}
                            title={paused ? 'Riprendi la rotazione' : 'Metti in pausa la rotazione'}
                            aria-label={paused ? 'Riprendi la rotazione' : 'Metti in pausa la rotazione'}
                            className="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-muted/60"
                        >
                            {paused ? <Play className="w-3 h-3" /> : <Pause className="w-3 h-3" />}
                        </button>
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            title="Insight precedente"
                            aria-label="Insight precedente"
                            className="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-muted/60"
                        >
                            <ChevronLeft className="w-3.5 h-3.5" />
                        </button>
                        <span className="text-[10px] text-muted-foreground tabular-nums" aria-hidden="true">
                            {idx + 1}/{total}
                        </span>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            title="Insight successivo"
                            aria-label="Insight successivo"
                            className="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-muted/60"
                        >
                            <ChevronRight className="w-3.5 h-3.5" />
                        </button>
                    </div>
                )}
            </CardHeader>
            {/* aria-live so a screen reader hears each insight as it comes up.
                The arrows and the counter carry the navigation, so no dot row —
                it would only cost height in a one-column cell. */}
            <CardContent className="p-3 pt-0 flex-1" aria-live="polite">
                <div key={current.key} className="flex items-start gap-2 animate-fade-in">
                    <Icon className="w-3.5 h-3.5 text-muted-foreground shrink-0 mt-0.5" />
                    <div className="min-w-0">
                        <p className="text-xs leading-snug">
                            <span className="text-muted-foreground">{current.label}: </span>
                            <span className="font-medium">{current.value}</span>
                        </p>
                        {current.hint && <p className="text-[11px] leading-snug text-muted-foreground mt-0.5">{current.hint}</p>}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
