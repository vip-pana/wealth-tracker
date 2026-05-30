import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import NetWorthLineChart from '@/Components/Charts/NetWorthLineChart';
import AllocationDonutChart from '@/Components/Charts/AllocationDonutChart';
import StackedBarChart from '@/Components/Charts/StackedBarChart';
import GrowthRateChart from '@/Components/Charts/GrowthRateChart';
import MonthComparisonChart from '@/Components/Charts/MonthComparisonChart';
import ForecastChart from '@/Components/Charts/ForecastChart';
import { Card, CardContent } from '@/Components/ui/card';
import { formatCurrency, formatPercent, formatDateLong } from '@/lib/formatters';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { PlusSquare, TrendingUp, TrendingDown, Minus, Target } from 'lucide-react';
import type { NetWorthPoint, AllocationSlice, StackedBarPoint, GrowthRatePoint, MonthComparisonPoint, ForecastPoint, MacroAllocationSlice, MacroStackedBarPoint, MacroComparisonPoint } from '@/types/analytics';
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
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    hasData: boolean;
    latestSnapshot: string | null;
    goal: { name: string; target_value: number; target_date: string | null } | null;
}

function SummaryCard({
    label,
    value,
    change,
}: {
    label: string;
    value: string;
    change?: number | null;
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
                        {formatPercent(change)} vs mese prec.
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
    categories,
    hasData,
    latestSnapshot,
    goal,
}: Props) {
    const [macroMode, setMacroMode] = useState(false);
    if (!hasData) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="flex flex-col items-center justify-center h-full gap-4 text-center p-8">
                    <div className="rounded-full bg-muted p-6">
                        <TrendingUp className="w-12 h-12 text-muted-foreground" />
                    </div>
                    <h2 className="text-xl font-semibold">Nessun dato ancora</h2>
                    <p className="text-muted-foreground max-w-sm">
                        Aggiungi i tuoi asset e salva il primo snapshot mensile per visualizzare i grafici.
                    </p>
                    <Link href="/input">
                        <Button>
                            <PlusSquare className="w-4 h-4 mr-2" />
                            Aggiungi dati
                        </Button>
                    </Link>
                </div>
            </>
        );
    }

    const lastPoint = netWorthSeries[netWorthSeries.length - 1];
    const prevPoint = netWorthSeries[netWorthSeries.length - 2];
    const totalChange = prevPoint && lastPoint
        ? ((lastPoint.total_value - prevPoint.total_value) / prevPoint.total_value) * 100
        : null;

    // Get the two most recent snapshot dates for comparison chart
    const snapshotMonths: [string, string] | null =
        netWorthSeries.length >= 2
            ? [
                netWorthSeries[netWorthSeries.length - 2].date,
                netWorthSeries[netWorthSeries.length - 1].date,
              ]
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

    const macroComparisonPoints: MonthComparisonPoint[] = macroMonthComparison.map((p) => ({
        category: p.macro,
        color: MACRO_COLORS[p.macro] ?? '#94a3b8',
        current: p.current,
        previous: p.previous,
    }));

    return (
        <>
            <Head title="Dashboard" />
            <div className="p-4 space-y-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-lg font-bold">Dashboard</h1>
                        {latestSnapshot && (
                            <p className="text-sm text-muted-foreground">
                                Ultimo aggiornamento: {formatDateLong(latestSnapshot)}
                            </p>
                        )}
                    </div>
                    <div className="flex items-center rounded-lg border border-border overflow-hidden text-sm">
                        <button
                            onClick={() => setMacroMode(false)}
                            className={`px-3 py-1.5 ${!macroMode ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Categorie
                        </button>
                        <button
                            onClick={() => setMacroMode(true)}
                            className={`px-3 py-1.5 ${macroMode ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Macro
                        </button>
                    </div>
                </div>

                {/* Summary cards + goal */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-2">
                    <SummaryCard
                        label="Patrimonio attuale"
                        value={lastPoint ? formatCurrency(lastPoint.total_value) : '—'}
                        change={totalChange}
                    />
                    {goal && lastPoint ? (
                        <Link href="/goal" className="contents">
                            <Card className="col-span-1 lg:col-span-3 border-amber-500/30 bg-amber-500/5 hover:bg-amber-500/10 transition-colors cursor-pointer">
                                <CardContent className="p-3 h-full flex items-center gap-4">
                                    <Target className="w-5 h-5 text-amber-500 flex-shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center justify-between mb-1.5">
                                            <span className="text-sm font-medium truncate">{goal.name}</span>
                                            <span className="text-xs text-muted-foreground ml-2 flex-shrink-0">
                                                {formatCurrency(lastPoint.total_value)} / {formatCurrency(goal.target_value)}
                                                {goal.target_date && ` · ${goal.target_date.slice(0, 4)}`}
                                            </span>
                                        </div>
                                        <div className="w-full bg-muted rounded-full h-1.5">
                                            <div
                                                className="bg-amber-500 h-1.5 rounded-full transition-all"
                                                style={{ width: `${Math.min(100, (lastPoint.total_value / goal.target_value) * 100).toFixed(1)}%` }}
                                            />
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
                                value={formatCurrency(slice.value)}
                            />
                        ))
                    )}
                </div>

                {/* Charts grid */}
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <NetWorthLineChart data={netWorthSeries} goalTarget={goal?.target_value} goalName={goal?.name} />
                    <AllocationDonutChart data={macroMode ? macroAllocationWithColor : allocationData} />
                    <GrowthRateChart data={growthRates} />
                </div>
                <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
                    <StackedBarChart data={macroMode ? macroStackedBar : stackedBar} categories={macroMode ? macroCategories : categories} />
                    <MonthComparisonChart data={macroMode ? macroComparisonPoints : monthComparison} months={snapshotMonths} />
                    <ForecastChart data={forecast} />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
