import { Head } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import NetWorthLineChart from '@/Components/Charts/NetWorthLineChart';
import AllocationDonutChart from '@/Components/Charts/AllocationDonutChart';
import StackedBarChart from '@/Components/Charts/StackedBarChart';
import GrowthRateChart from '@/Components/Charts/GrowthRateChart';
import MonthComparisonChart from '@/Components/Charts/MonthComparisonChart';
import ForecastChart from '@/Components/Charts/ForecastChart';
import { Card, CardContent } from '@/Components/ui/card';
import { formatCurrency, formatPercent, formatMonthLong } from '@/lib/formatters';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { PlusSquare, TrendingUp, TrendingDown, Minus } from 'lucide-react';
import type { NetWorthPoint, AllocationSlice, StackedBarPoint, GrowthRatePoint, MonthComparisonPoint, ForecastPoint } from '@/types/analytics';
import type { Category } from '@/types/models';

interface Props {
    netWorthSeries: NetWorthPoint[];
    allocationData: AllocationSlice[];
    stackedBar: StackedBarPoint[];
    growthRates: GrowthRatePoint[];
    monthComparison: MonthComparisonPoint[];
    forecast: ForecastPoint[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    hasData: boolean;
    latestSnapshot: string | null;
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
            <CardContent className="p-4">
                <p className="text-xs text-muted-foreground mb-1">{label}</p>
                <p className="text-xl font-bold">{value}</p>
                {change != null && Icon && (
                    <p className={`text-xs flex items-center gap-1 mt-1 ${color}`}>
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
    categories,
    hasData,
    latestSnapshot,
}: Props) {
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
    const latestGrowth = growthRates[growthRates.length - 1];
    const totalChange = prevPoint && lastPoint
        ? ((lastPoint.total_value - prevPoint.total_value) / prevPoint.total_value) * 100
        : null;

    // Get the two most recent snapshot months for comparison chart
    const snapshotMonths: [string, string] | null =
        netWorthSeries.length >= 2
            ? [
                netWorthSeries[netWorthSeries.length - 2].month,
                netWorthSeries[netWorthSeries.length - 1].month,
              ]
            : null;

    return (
        <>
            <Head title="Dashboard" />
            <div className="p-6 space-y-6">
                <div>
                    <h1 className="text-2xl font-bold">Dashboard</h1>
                    {latestSnapshot && (
                        <p className="text-sm text-muted-foreground">
                            Ultimo aggiornamento: {formatMonthLong(latestSnapshot)}
                        </p>
                    )}
                </div>

                {/* Summary cards */}
                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <SummaryCard
                        label="Patrimonio attuale"
                        value={lastPoint ? formatCurrency(lastPoint.total_value) : '—'}
                        change={totalChange}
                    />
                    {allocationData.slice(0, 3).map((slice) => (
                        <SummaryCard
                            key={slice.name}
                            label={slice.name}
                            value={formatCurrency(slice.value)}
                        />
                    ))}
                </div>

                {/* Charts grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <NetWorthLineChart data={netWorthSeries} />
                    <AllocationDonutChart data={allocationData} />
                    <StackedBarChart data={stackedBar} categories={categories} />
                    <GrowthRateChart data={growthRates} />
                    <MonthComparisonChart data={monthComparison} months={snapshotMonths} />
                    <ForecastChart data={forecast} />
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
