import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import NetWorthLineChart from '@/Components/Charts/NetWorthLineChart';
import AllocationDonutChart from '@/Components/Charts/AllocationDonutChart';
import StackedBarChart from '@/Components/Charts/StackedBarChart';
import GrowthRateChart from '@/Components/Charts/GrowthRateChart';
import MonthComparisonChart from '@/Components/Charts/MonthComparisonChart';
import ForecastChart from '@/Components/Charts/ForecastChart';
import PortfolioInsights from '@/Components/Dashboard/PortfolioInsights';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Card, CardContent } from '@/Components/ui/card';
import { Money } from '@/Components/ui/Money';
import { formatPercent, formatDateLong } from '@/lib/formatters';
import { netWorthChangePct } from '@/lib/metrics';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { PlusSquare, TrendingUp, TrendingDown, Minus, Target, LayoutDashboard } from 'lucide-react';
import type { NetWorthPoint, AllocationSlice, StackedBarPoint, GrowthRatePoint, MonthComparisonPoint, ForecastPoint, MacroAllocationSlice, MacroStackedBarPoint, MacroComparisonPoint, PortfolioMetrics, PositionReturns } from '@/types/analytics';
import type { Category } from '@/types/models';

const MACRO_COLORS: Record<string, string> = {
    'Liquidità': '#60a5fa',
    'ETF':       '#34d399',
    'Cripto':    '#f59e0b',
};

interface Props {
    netWorthSeries: NetWorthPoint[];
    allocationData: AllocationSlice[];
    stackedBar: StackedBarPoint[];
    growthRates: GrowthRatePoint[];
    monthComparison: MonthComparisonPoint[];
    forecast: ForecastPoint[];
    macroAllocationData: MacroAllocationSlice[];
    macroStackedBar: MacroStackedBarPoint[];
    macroMonthComparison: MacroComparisonPoint[];
    momNetWorthSeries: NetWorthPoint[];
    momStackedBar: StackedBarPoint[];
    momGrowthRates: GrowthRatePoint[];
    momMonthComparison: MonthComparisonPoint[];
    momForecast: ForecastPoint[];
    momMacroStackedBar: MacroStackedBarPoint[];
    momMacroMonthComparison: MacroComparisonPoint[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    hasData: boolean;
    hasBuffer: boolean;
    hasIlliquid: boolean;
    latestSnapshot: string | null;
    goal: { name: string; target_value: number; target_date: string | null; milestones: { target_value: number }[] } | null;
    portfolioMetrics: PortfolioMetrics;
    positionReturns: PositionReturns | null;
}

function SummaryCard({
    label,
    value,
    change,
    changeLabel,
}: {
    label: string;
    value: React.ReactNode;
    change?: number | null;
    changeLabel?: string;
}) {
    const Icon = change == null ? null : change > 0 ? TrendingUp : change < 0 ? TrendingDown : Minus;
    const color = change == null ? '' : change > 0 ? 'text-green-500' : change < 0 ? 'text-red-500' : 'text-muted-foreground';

    return (
        <Card>
            <CardContent className="p-3">
                <p className="text-xs text-muted-foreground mb-0.5">{label}</p>
                <p className="text-base font-bold">{value}</p>
                {change != null && Icon && (
                    <p className={`text-xs flex items-center gap-1 mt-0.5 ${color}`}>
                        <Icon className="w-3 h-3" />
                        {formatPercent(change)} {changeLabel}
                    </p>
                )}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    netWorthSeries,
    allocationData,
    stackedBar,
    growthRates,
    monthComparison,
    forecast,
    macroAllocationData,
    macroStackedBar,
    macroMonthComparison,
    momNetWorthSeries,
    momStackedBar,
    momGrowthRates,
    momMonthComparison,
    momForecast,
    momMacroStackedBar,
    momMacroMonthComparison,
    categories,
    hasData,
    hasBuffer,
    hasIlliquid,
    latestSnapshot,
    goal,
    portfolioMetrics,
    positionReturns,
}: Props) {
    const [macroMode, setMacroMode] = useState(false);
    const [momMode, setMomMode] = useState(false);
    if (!hasData) {
        return (
            <>
                <Head title="Dashboard" />
                <EmptyState
                    icon={TrendingUp}
                    title="Nessun dato ancora"
                    description="Aggiungi i tuoi asset e salva il primo snapshot mensile per visualizzare i grafici."
                    action={
                        <Link href="/input">
                            <Button>
                                <PlusSquare className="w-4 h-4 mr-2" />
                                Aggiungi dati
                            </Button>
                        </Link>
                    }
                />
            </>
        );
    }

    const series = momMode ? momNetWorthSeries : netWorthSeries;
    const lastPoint = series[series.length - 1];
    const prevPoint = series[series.length - 2];
    const totalChange = netWorthChangePct(prevPoint?.total_value, lastPoint?.total_value);

    // The investment charts below (composition, variation, forecast) show the
    // INVESTABLE portfolio — pension and the emergency-fund buffer are carved
    // out. Spell that out under their titles, but only when there's actually
    // something excluded (otherwise "investable" just means "everything").
    const excluded = [hasBuffer ? 'fondo emergenza' : null, hasIlliquid ? 'fondo pensione' : null].filter(Boolean);
    const investableNote = excluded.length > 0 ? `Solo parte investibile — esclude ${excluded.join(' e ')}` : undefined;

    // Get the two most recent dates for the comparison chart
    const snapshotMonths: [string, string] | null =
        series.length >= 2
            ? [series[series.length - 2].date, series[series.length - 1].date]
            : null;

    const macroAllocationWithColor: AllocationSlice[] = macroAllocationData.map((s) => ({
        ...s,
        color: MACRO_COLORS[s.name] ?? '#94a3b8',
    }));

    const macroCategories = Object.entries(MACRO_COLORS).map(([name, color]) => ({
        id: 0,
        name,
        color,
    }));

    const macroComparisonPoints: MonthComparisonPoint[] = (momMode ? momMacroMonthComparison : macroMonthComparison).map((p) => ({
        category: p.macro,
        color: MACRO_COLORS[p.macro] ?? '#94a3b8',
        current: p.current,
        previous: p.previous,
    }));

    return (
        <>
            <Head title="Dashboard" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={LayoutDashboard}
                    title="Dashboard"
                    subtitle={latestSnapshot ? `Ultimo aggiornamento: ${formatDateLong(latestSnapshot)}` : undefined}
                    actions={
                        <div className="flex gap-2">
                            <SegmentedToggle
                                options={[
                                    { value: 'snapshot', label: 'Snapshot' },
                                    { value: 'mom', label: 'Mese' },
                                ]}
                                value={momMode ? 'mom' : 'snapshot'}
                                onChange={(v) => setMomMode(v === 'mom')}
                            />
                            <SegmentedToggle
                                options={[
                                    { value: 'category', label: 'Categorie' },
                                    { value: 'macro', label: 'Macro' },
                                ]}
                                value={macroMode ? 'macro' : 'category'}
                                onChange={(v) => setMacroMode(v === 'macro')}
                            />
                        </div>
                    }
                />

                {/* Summary cards + goal */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2">
                    <SummaryCard
                        label="Patrimonio attuale"
                        value={lastPoint ? <Money value={lastPoint.total_value} /> : '—'}
                        change={totalChange}
                        changeLabel={momMode ? 'vs mese prec.' : 'vs snapshot prec.'}
                    />
                    {goal && lastPoint ? (
                        <Link href="/goal" className="contents">
                            <Card className="sm:col-span-1 lg:col-span-3 border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10 transition-colors cursor-pointer">
                                <CardContent className="p-3 h-full flex items-center gap-4">
                                    <Target className="w-5 h-5 text-amber-500 flex-shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span className="text-sm font-medium truncate">{goal.name}</span>
                                            <span className="text-xs text-muted-foreground ml-2 flex-shrink-0">
                                                <Money value={lastPoint.total_value} /> / <Money value={goal.target_value} />
                                                {goal.target_date && ` · ${goal.target_date.slice(0, 4)}`}
                                            </span>
                                        </div>
                                        <div className="relative w-full bg-muted rounded-full h-1.5">
                                            <div
                                                className="bg-amber-500 h-1.5 rounded-full transition-all"
                                                style={{ width: `${Math.min(100, (lastPoint.total_value / goal.target_value) * 100).toFixed(1)}%` }}
                                            />
                                            {goal.target_value > 0 && goal.milestones.map((m, i) => (
                                                <span
                                                    key={i}
                                                    className={`absolute top-1/2 h-2.5 w-0.5 -translate-x-1/2 -translate-y-1/2 rounded-full ${lastPoint.total_value >= m.target_value ? 'bg-emerald-500' : 'bg-foreground/30'}`}
                                                    style={{ left: `${Math.min((m.target_value / goal.target_value) * 100, 100)}%` }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                    <span className="text-sm font-bold text-amber-500 flex-shrink-0">
                                        {((lastPoint.total_value / goal.target_value) * 100).toFixed(1)}%
                                    </span>
                                </CardContent>
                            </Card>
                        </Link>
                    ) : (
                        (macroMode ? macroAllocationWithColor : allocationData).slice(0, 3).map((slice) => (
                            <SummaryCard
                                key={slice.name}
                                label={slice.name}
                                value={<Money value={slice.value} />}
                            />
                        ))
                    )}
                </div>

                {/* Portfolio reading (rule-based today; AI advisor builds on these metrics) */}
                <PortfolioInsights metrics={portfolioMetrics} positionReturns={positionReturns} />

                {/* Charts grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <NetWorthLineChart data={series} goalTarget={goal?.target_value} goalName={goal?.name} />
                    <AllocationDonutChart data={macroMode ? macroAllocationWithColor : allocationData} note={investableNote} />
                    <GrowthRateChart data={momMode ? momGrowthRates : growthRates} title={momMode ? 'Variazione mensile (%)' : 'Variazione tra snapshot (%)'} note={investableNote} />
                </div>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <StackedBarChart data={macroMode ? (momMode ? momMacroStackedBar : macroStackedBar) : (momMode ? momStackedBar : stackedBar)} categories={macroMode ? macroCategories : categories} note={investableNote} />
                    <MonthComparisonChart data={macroMode ? macroComparisonPoints : (momMode ? momMonthComparison : monthComparison)} months={snapshotMonths} title={momMode ? 'Confronto tra mesi' : 'Confronto tra snapshot'} note={investableNote} />
                    <ForecastChart data={momMode ? momForecast : forecast} note={investableNote} />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
