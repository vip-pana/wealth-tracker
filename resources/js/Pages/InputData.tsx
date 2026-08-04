import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import AssetForm from '@/Components/Data/AssetForm';
import AssetTable from '@/Components/Data/AssetTable';
import AddAssetDialog, { type CopyableAsset } from '@/Components/Data/AddAssetDialog';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/Components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/Components/ui/tooltip';
import { Plus, ChevronLeft, ChevronRight, Camera, Scale, HelpCircle, CalendarDays, Lock, RefreshCw } from 'lucide-react';
import { formatMonthLong, formatDateLong, today, stepMonth, currentMonth } from '@/lib/formatters';
import { DeltaAmount } from '@/Components/Data/DeltaAmount';
import { categoryDelta, monthsSince } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import { Money } from '@/Components/ui/Money';
import { NetWorthBreakdown, type Reconciliation, type CarriedForward } from '@/Components/Data/NetWorthReconciliation';
import { SnapshotDiff, type SnapshotDiff as SnapshotDiffData } from '@/Components/Data/SnapshotDiff';
import type { Asset, AssetPriceInfo, Category } from '@/types/models';

interface Props {
    assets: Asset[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    month: string;
    availableMonths: string[];
    snapshotState: 'missing' | 'stale' | 'current';
    currentNetWorth: number;
    reconciliation: Reconciliation;
    // What a snapshot saved now would change. Null before the first snapshot.
    snapshotDiff: SnapshotDiffData | null;
    prices: Record<string, AssetPriceInfo>;
    previousValues: Record<string, number>;
    // Latest tracked month before this one — not necessarily the previous
    // calendar month. Null on the first tracked month.
    previousMonth: string | null;
    copyableAssets: CopyableAsset[];
}

const PAST_MONTH_HINT = 'I mesi passati sono in sola lettura: torna al mese corrente per modificare gli asset.';

// Why the snapshot button looks the way it does. 'missing' used to render no
// badge at all — the worst state was the silent one — so it gets a voice here.
const SNAPSHOT_HINTS: Record<string, string> = {
    empty: 'Aggiungi almeno un asset per salvare uno snapshot.',
    missing: 'Nessuno snapshot in questo mese: il patrimonio attuale non è ancora fissato nei grafici.',
    stale: 'Hai modificato degli asset dopo l’ultimo snapshot: salvane uno nuovo per aggiornare i grafici.',
    current: 'I grafici sono allineati agli asset di questo mese. Puoi comunque salvare un nuovo snapshot.',
};

// Explanatory copy that used to sit permanently under each card title, moved
// behind a hover/focus target so the cards lead with their numbers.
function HelpHint({ text }: { text: string }) {
    return (
        <TooltipProvider delayDuration={100}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <button type="button" aria-label={text} className="text-muted-foreground hover:text-foreground">
                        <HelpCircle className="w-3.5 h-3.5" />
                    </button>
                </TooltipTrigger>
                <TooltipContent className="max-w-xs text-xs">{text}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

/**
 * A category with no row this month, shown among the live category cards so its
 * absence is visible by contrast rather than buried in a separate breakdown.
 * The value is its last known one; clicking starts an asset for it.
 */
function StaleCategoryCard({ item, onClick, disabled }: { item: CarriedForward; onClick: () => void; disabled?: boolean }) {
    const months = monthsSince(item.asOf);
    const severe = months >= 3;

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={`${item.category} non ha valori in questo mese: conta ancora con il valore di ${formatMonthLong(item.asOf)}. Clicca per aggiornarla.`}
            className={cn(
                'rounded-lg border border-dashed p-2 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-50',
                severe ? 'border-red-500/50 hover:bg-red-500/5' : 'border-amber-500/50 hover:bg-amber-500/5',
            )}
        >
            <p className="flex items-center gap-1.5 min-w-0 text-xs text-muted-foreground">
                <span
                    className="h-2 w-2 shrink-0 rounded-full opacity-40"
                    style={{ backgroundColor: item.color }}
                />
                <span className="truncate">{item.category}</span>
            </p>
            <p className="text-sm font-semibold tabular-nums text-muted-foreground">
                <Money value={item.value} />
            </p>
            <p className={cn('text-xs', severe ? 'text-red-500' : 'text-amber-500')}>
                da {formatMonthLong(item.asOf)}
                {months > 0 && <> · {months} {months === 1 ? 'mese' : 'mesi'}</>}
            </p>
        </button>
    );
}

export default function InputData({ assets, categories, month, availableMonths, snapshotState, currentNetWorth, reconciliation, snapshotDiff, prices, previousValues, previousMonth, copyableAssets }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [addOpen, setAddOpen] = useState(false);
    const [editAsset, setEditAsset] = useState<Asset | null>(null);
    // Set when the form is opened from a stale category card, so the new asset
    // starts on the category the user was told to update.
    const [prefillCategoryId, setPrefillCategoryId] = useState<number | null>(null);
    const [savingSnapshot, setSavingSnapshot] = useState(false);
    const [refreshingPrices, setRefreshingPrices] = useState(false);
    const [snapshotOpen, setSnapshotOpen] = useState(false);

    // The button is disabled with no assets, so the dialog only ever confirms a
    // save that can actually go through.
    const handleSaveSnapshot = () => {
        setSavingSnapshot(true);
        router.post('/snapshots', { date: today() }, {
            onFinish: () => {
                setSavingSnapshot(false);
                setSnapshotOpen(false);
            },
        });
    };

    // Re-reads market prices, bank balances and broker values. The controller
    // reports the outcome through a flash message, so nothing to handle here
    // beyond the spinner.
    const handleRefreshPrices = () => {
        setRefreshingPrices(true);
        router.post('/prices/refresh', {}, {
            preserveScroll: true,
            onFinish: () => setRefreshingPrices(false),
        });
    };

    const handleMonthChange = (newMonth: string) => {
        router.get('/input', { month: newMonth }, { preserveState: false });
    };

    const navigateMonth = (direction: 'prev' | 'next') => {
        handleMonthChange(stepMonth(month, direction));
    };

    // A past month is a record, not a worksheet: it can be read but not edited.
    // The current month and any future one stay editable, so next month can be
    // prepared ahead of time.
    const isPast = month < currentMonth();

    const total = assets.reduce((sum, a) => sum + a.value, 0);

    // Group by category for summary
    const byCat: Record<string, { name: string; color: string; total: number; assets: Asset[] }> = {};
    for (const a of assets) {
        if (!byCat[a.category_id]) {
            byCat[a.category_id] = {
                name:  a.category.name,
                color: a.category.color,
                total: 0,
                assets: [],
            };
        }
        byCat[a.category_id].total += a.value;
        byCat[a.category_id].assets.push(a);
    }

    return (
        <>
            <Head title="Bilancio investimenti" />
            {/* Full-height column: the cards above take what they need and the
                asset table absorbs the rest, so only the rows scroll. */}
            <div className="flex flex-col flex-1 min-h-0 p-4 gap-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={Scale}
                    title="Bilancio investimenti"
                    subtitle="Aggiorna gli asset del mese e salva uno snapshot per fissarli nei grafici."
                />

                {/* Header: the month's asset total, broken down by category */}
                <div className="shrink-0">
                    <Card>
                        <CardContent className="p-3 space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <p className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                                    Totale asset
                                    <HelpHint text="Aggiorna qui il valore corrente di ogni asset. Questi numeri non finiscono nei grafici finché non salvi uno snapshot." />
                                </p>
                                <div className="flex items-center gap-1">
                                    <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateMonth('prev')}>
                                        <ChevronLeft className="w-4 h-4" />
                                    </Button>
                                    <Select value={month} onValueChange={handleMonthChange}>
                                        <SelectTrigger className="h-7 w-44 px-2 whitespace-nowrap">
                                            <SelectValue>{formatMonthLong(month)}</SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableMonths.map((m) => (
                                                <SelectItem key={m} value={m}>
                                                    {formatMonthLong(m)}
                                                </SelectItem>
                                            ))}
                                            {!availableMonths.includes(month) && (
                                                <SelectItem value={month}>{formatMonthLong(month)}</SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateMonth('next')}>
                                        <ChevronRight className="w-4 h-4" />
                                    </Button>
                                    {/* Only useful once you have wandered off it. */}
                                    {month !== currentMonth() && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            className="h-7"
                                            onClick={() => handleMonthChange(currentMonth())}
                                            title={`Torna a ${formatMonthLong(currentMonth())}`}
                                        >
                                            <CalendarDays className="w-4 h-4 mr-1" />
                                            Oggi
                                        </Button>
                                    )}
                                </div>
                            </div>
                            {isPast && (
                                <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Lock className="w-3.5 h-3.5 shrink-0" />
                                    Mese passato, in sola lettura.
                                </p>
                            )}
                            <p className="text-2xl font-bold"><Money value={total} /></p>
                            {assets.length > 0 && (
                                <div className="space-y-1.5 pt-1">
                                    <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                        Per categoria{previousMonth && <> · variazione vs {formatMonthLong(previousMonth)}</>}
                                    </p>
                                    {/* Five per row, but a short row stretches to fill
                                        rather than leaving a hole on the right. */}
                                    <div className="flex flex-wrap gap-2 *:min-w-[calc((100%-2rem)/5)] *:flex-1">
                                        {Object.values(byCat).map((cat) => (
                                            <div key={cat.name} className="rounded-lg border bg-muted/40 p-2">
                                                <p className="flex items-center gap-1.5 min-w-0 text-xs text-muted-foreground">
                                                    <span
                                                        className="h-2 w-2 shrink-0 rounded-full"
                                                        style={{ backgroundColor: cat.color }}
                                                    />
                                                    <span className="truncate">{cat.name}</span>
                                                </p>
                                                <p className="text-sm font-semibold tabular-nums">
                                                    <Money value={cat.total} />
                                                </p>
                                                <p className="text-xs tabular-nums">
                                                    <DeltaAmount change={categoryDelta(cat.assets, previousValues)} />
                                                </p>
                                            </div>
                                        ))}
                                        {/* These prompt an edit, so they belong to an
                                            editable month only. */}
                                        {!isPast && reconciliation.carriedForward.map((item) => (
                                            <StaleCategoryCard
                                                key={item.categoryId}
                                                item={item}
                                                disabled={refreshingPrices}
                                                onClick={() => {
                                                    setEditAsset(null);
                                                    setPrefillCategoryId(item.categoryId);
                                                    setFormOpen(true);
                                                }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                </div>

                {/* Main asset card */}
                <Card className="flex flex-col min-h-0 flex-1">
                    <CardHeader className="flex flex-row items-center justify-between pb-3 shrink-0">
                        <CardTitle className="text-base">
                            Asset — {formatMonthLong(month)}
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="default"
                                size="sm"
                                onClick={() => setAddOpen(true)}
                                disabled={isPast || refreshingPrices}
                                title={isPast ? PAST_MONTH_HINT : undefined}
                            >
                                <Plus className="w-4 h-4 mr-1" />
                                Aggiungi
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setSnapshotOpen(true)}
                                disabled={savingSnapshot || assets.length === 0 || isPast || refreshingPrices}
                                title={isPast ? PAST_MONTH_HINT : SNAPSHOT_HINTS[assets.length === 0 ? 'empty' : snapshotState]}
                            >
                                <Camera className="w-4 h-4 mr-1" />
                                {snapshotState === 'current' ? 'Snapshot salvato' : 'Salva snapshot'}
                                {/* A dot rather than a coloured button: this is a
                                    state to notice, not the page's main action. */}
                                {snapshotState !== 'current' && assets.length > 0 && !isPast && (
                                    <span
                                        className="ml-1.5 h-2 w-2 shrink-0 rounded-full bg-amber-500"
                                        aria-hidden
                                    />
                                )}
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                onClick={handleRefreshPrices}
                                disabled={refreshingPrices || isPast}
                                aria-label="Aggiorna i valori degli asset"
                            >
                                <RefreshCw className={cn('w-4 h-4', refreshingPrices && 'animate-spin')} />
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0 flex flex-col min-h-0 flex-1">
                        <AssetTable
                            assets={assets}
                            onEdit={(a) => {
                                setEditAsset(a);
                                setPrefillCategoryId(null);
                                setFormOpen(true);
                            }}
                            prices={prices}
                            previousValues={previousValues}
                            previousMonth={previousMonth}
                            currentNetWorth={currentNetWorth}
                            reconciliation={reconciliation}
                            readOnly={isPast || refreshingPrices}
                            pastMonth={isPast}
                        />
                    </CardContent>
                </Card>

            </div>

            <AssetForm
                open={formOpen}
                onClose={() => {
                    setFormOpen(false);
                    setEditAsset(null);
                    setPrefillCategoryId(null);
                }}
                categories={categories}
                month={month}
                editAsset={editAsset}
                prefillCategoryId={prefillCategoryId}
                prices={prices}
                previousValues={previousValues}
            />

            <Dialog open={snapshotOpen} onOpenChange={setSnapshotOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Salva snapshot</DialogTitle>
                        <DialogDescription className="sr-only">Conferma il salvataggio dello snapshot del patrimonio di oggi.</DialogDescription>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Il patrimonio di <strong className="text-foreground"><Money value={currentNetWorth} /></strong> verrà
                        fissato nei grafici alla data di oggi, {formatDateLong(today())}.
                    </p>
                    {/* What this snapshot would change, so the save isn't blind. */}
                    {snapshotDiff !== null && <SnapshotDiff diff={snapshotDiff} />}
                    {/* Only worth explaining the gap between the month's assets
                        and net worth when there actually is one. */}
                    {reconciliation.carriedForward.length > 0 && (
                        <NetWorthBreakdown reconciliation={reconciliation} month={month} />
                    )}
                    {snapshotDiff === null && (
                        <p className="text-xs text-muted-foreground">Sarà il tuo primo snapshot.</p>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setSnapshotOpen(false)} disabled={savingSnapshot}>
                            Annulla
                        </Button>
                        <Button onClick={handleSaveSnapshot} disabled={savingSnapshot}>
                            {savingSnapshot ? 'Salvataggio…' : 'Salva'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AddAssetDialog
                open={addOpen}
                onClose={() => setAddOpen(false)}
                onManual={() => {
                    setEditAsset(null);
                    setPrefillCategoryId(null);
                    setFormOpen(true);
                }}
                month={month}
                previousMonth={previousMonth}
                copyableAssets={copyableAssets}
            />
        </>
    );
}

InputData.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
