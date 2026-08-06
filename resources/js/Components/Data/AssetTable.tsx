import { useState } from 'react';
import { Pencil, Landmark, CandlestickChart, AlertTriangle, ReceiptText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { DeltaAmount } from '@/Components/Data/DeltaAmount';
import { NetWorthReconciliation, type Reconciliation } from '@/Components/Data/NetWorthReconciliation';
import { priceFreshness, bankFreshness, brokerFreshness, monthDelta, categoryDelta } from '@/lib/metrics';
import { formatMonthLong } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import TransactionsDialog from '@/Components/Data/TransactionsDialog';
import { Money } from '@/Components/ui/Money';
import type { Asset, AssetPriceInfo } from '@/types/models';

interface Props {
    assets: Asset[];
    onEdit: (asset: Asset) => void;
    prices: Record<string, AssetPriceInfo>;
    // Value of each asset in the most recent month before this one, keyed
    // "category_id|name" (see FetchInputData::previousValues).
    previousValues: Record<string, number>;
    // The month those values come from — the latest tracked month before the
    // current one, not necessarily the previous calendar month. Null on the
    // first tracked month, where the column has nothing to compare against.
    previousMonth: string | null;
    // Net worth as of today, which exceeds the month's asset total whenever a
    // category has no row this month and still counts at its last known value.
    currentNetWorth: number;
    reconciliation: Reconciliation;
    // Hides the per-row edit action. Set for a past month — a record, not a
    // worksheet — and while a price refresh is in flight, so nothing is edited
    // against values that are about to change under it.
    readOnly?: boolean;
    // Whether the month itself is closed, as opposed to temporarily locked.
    // Only the wording of the empty state depends on the difference.
    pastMonth?: boolean;
}


/**
 * Name, sync badges and the metadata lines under them. Shared by the table row
 * and the mobile card so a change to either layout cannot silently drop a
 * badge — this block is what tells you whether a figure can be trusted.
 */
function AssetIdentity({ asset, prices }: { asset: Asset; prices: Record<string, AssetPriceInfo> }) {
    const freshness = asset.ticker ? priceFreshness(prices[asset.ticker]?.fetched_at) : null;
    // Drive the bank badge off the live link state (same signal as the
    // edit-modal lock), and the freshness line off the last sync time.
    const bankSync = asset.bank_linked ? bankFreshness(asset.synced_at) : null;
    // A broker-synced holding (e.g. Scalable) surfaces its own freshness
    // so a stalled sync (expired session) shows as stale.
    const brokerSync = asset.sync_source === 'broker'
        ? brokerFreshness(asset.synced_at)
        : null;

    return (
        <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                <p className="font-medium">{asset.name}</p>
                {asset.ticker && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-mono',
                            freshness?.stale
                                ? 'bg-muted text-muted-foreground'
                                : 'bg-blue-500/10 text-blue-400',
                        )}
                        title={freshness?.label}
                    >
                        <span
                            className={cn(
                                'w-1.5 h-1.5 rounded-full',
                                freshness?.stale ? 'bg-muted-foreground' : 'bg-blue-400 animate-pulse',
                            )}
                        />
                        {asset.ticker}
                    </span>
                )}
                {bankSync && (
                    <span
                        className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-xs text-emerald-400"
                        title="Saldo sincronizzato dal conto bancario collegato"
                    >
                        <Landmark className="w-3 h-3" aria-hidden />
                        Banca
                    </span>
                )}
                {brokerSync && (
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs',
                            brokerSync.stale ? 'bg-amber-500/10 text-amber-500' : 'bg-indigo-500/10 text-indigo-400',
                        )}
                        title={brokerSync.stale
                            ? 'Sincronizzazione Scalable ferma: il proxy potrebbe essere spento o la sessione scaduta'
                            : 'Valore sincronizzato dal broker Scalable'}
                    >
                        {brokerSync.stale
                            ? <AlertTriangle className="w-3 h-3" aria-hidden />
                            : <CandlestickChart className="w-3 h-3" aria-hidden />}
                        {brokerSync.stale ? 'Scalable · non aggiornato' : 'Scalable'}
                    </span>
                )}
            </div>
            {asset.ticker && asset.quantity !== null && (
                <p className="text-xs text-muted-foreground">
                    {asset.quantity} unità
                    {asset.price !== null && (
                        <> · <Money value={asset.price} />/unità</>
                    )}
                    {freshness && (
                        <> · <span className={cn(freshness.stale && 'text-amber-500')}>{freshness.label}</span></>
                    )}
                    {asset.wallet_address && (
                        <> · <span className="font-mono" title={asset.wallet_address}>{asset.wallet_address.slice(0, 8)}…{asset.wallet_address.slice(-6)}</span></>
                    )}
                </p>
            )}
            {bankSync && (
                <p className="text-xs text-muted-foreground">
                    Saldo da banca · <span className={cn(bankSync.stale && 'text-amber-500')}>{bankSync.label}</span>
                </p>
            )}
            {brokerSync && (
                <p className="text-xs text-muted-foreground">
                    Valore da Scalable · <span className={cn(brokerSync.stale && 'text-amber-500')}>{brokerSync.label}</span>
                    {brokerSync.stale && <span className="text-amber-500"> · sincronizzazione ferma</span>}
                </p>
            )}
            {asset.notes && !asset.ticker && (
                <p className="text-xs text-muted-foreground">{asset.notes}</p>
            )}
        </div>
    );
}

function CategoryLabel({ asset }: { asset: Asset }) {
    return (
        <span className="flex min-w-0 items-center gap-1.5 text-sm text-muted-foreground">
            <span
                className="w-2 h-2 rounded-full shrink-0"
                style={{ backgroundColor: asset.category.color }}
            />
            <span className="truncate">{asset.category.name}</span>
        </span>
    );
}

function AssetActions({ asset, onEdit, onViewTransactions, readOnly, size }: { asset: Asset; onEdit: (a: Asset) => void; onViewTransactions: (a: Asset) => void; readOnly: boolean; size: 'sm' | 'touch' }) {
    const button = size === 'touch' ? 'h-9 w-9' : 'h-8 w-8';

    return (
        <div className="flex justify-end gap-1">
            {asset.transaction_managed && (
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(button, 'text-muted-foreground hover:text-foreground hover:bg-accent')}
                    title="Vedi transazioni"
                    onClick={() => onViewTransactions(asset)}
                >
                    <ReceiptText className="w-4 h-4" />
                </Button>
            )}
            {!readOnly && (
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn(button, 'text-muted-foreground hover:text-foreground hover:bg-accent')}
                    onClick={() => onEdit(asset)}
                >
                    <Pencil className="w-4 h-4" />
                </Button>
            )}
        </div>
    );
}

function AssetRow({ asset, onEdit, onViewTransactions, prices, previousValues, readOnly }: { asset: Asset; onEdit: (a: Asset) => void; onViewTransactions: (a: Asset) => void; prices: Record<string, AssetPriceInfo>; previousValues: Record<string, number>; readOnly: boolean }) {
    return (
        <TableRow>
            <TableCell>
                <AssetIdentity asset={asset} prices={prices} />
            </TableCell>
            <TableCell>
                <CategoryLabel asset={asset} />
            </TableCell>
            <TableCell className="text-right font-mono">
                <Money value={asset.value} />
            </TableCell>
            <TableCell className="text-right font-mono whitespace-nowrap">
                <DeltaAmount change={monthDelta(asset, previousValues)} />
            </TableCell>
            <TableCell className="text-right">
                <AssetActions asset={asset} onEdit={onEdit} onViewTransactions={onViewTransactions} readOnly={readOnly} size="sm" />
            </TableCell>
        </TableRow>
    );
}

/**
 * The same asset as a card. Five columns do not survive a phone: the value and
 * its change move under the name instead of beside it, and the category becomes
 * a label rather than a column.
 */
function AssetCard({ asset, onEdit, onViewTransactions, prices, previousValues, readOnly }: { asset: Asset; onEdit: (a: Asset) => void; onViewTransactions: (a: Asset) => void; prices: Record<string, AssetPriceInfo>; previousValues: Record<string, number>; readOnly: boolean }) {
    return (
        <div className="rounded-lg border p-3">
            <AssetIdentity asset={asset} prices={prices} />
            <div className="mt-2 flex items-end justify-between gap-2">
                <CategoryLabel asset={asset} />
                <div className="shrink-0 text-right">
                    <p className="font-mono font-medium">
                        <Money value={asset.value} />
                    </p>
                    <p className="font-mono text-xs whitespace-nowrap">
                        <DeltaAmount change={monthDelta(asset, previousValues)} />
                    </p>
                </div>
            </div>
            {(asset.transaction_managed || !readOnly) && (
                <div className="mt-1">
                    <AssetActions asset={asset} onEdit={onEdit} onViewTransactions={onViewTransactions} readOnly={readOnly} size="touch" />
                </div>
            )}
        </div>
    );
}

export default function AssetTable({ assets, onEdit, prices, previousValues, previousMonth, currentNetWorth, reconciliation, readOnly = false, pastMonth = false }: Props) {
    const [txAsset, setTxAsset] = useState<Asset | null>(null);

    if (assets.length === 0) {
        return (
            <div className="px-4 py-12 text-center text-muted-foreground text-sm">
                {pastMonth
                    ? 'Nessun asset registrato in questo mese.'
                    : 'Nessun asset per questo mese. Aggiungine uno con il pulsante sopra.'}
            </div>
        );
    }

    const total = assets.reduce((sum, a) => sum + a.value, 0);
    // Only assets with a previous value count, so adding an asset this month
    // doesn't read as growth — and the percentage is over that same comparable
    // base, not over `total`, which would understate the move.
    const totalDelta = categoryDelta(assets, previousValues);

    // The table is flat, but assets of the same category still sit together:
    // group by category_id in first-seen order, then flatten.
    const groups = new Map<number, Asset[]>();
    for (const asset of assets) {
        if (!groups.has(asset.category_id)) groups.set(asset.category_id, []);
        groups.get(asset.category_id)!.push(asset);
    }
    const ordered = [...groups.values()].flat();

    return (
        <div className="flex flex-col sm:min-h-0 sm:flex-1">
            {/* The rows scroll, not the page: the header stays put and the
                totals stay pinned to the bottom, so the figures you are editing
                against never leave the screen. The height comes from the parent
                flex chain rather than a vh guess, so the page itself never
                overflows however tall the cards above happen to be. */}
            <div className="hidden flex-1 min-h-0 overflow-y-auto sm:block">
                <Table>
                    <TableHeader className="sticky top-0 z-10 bg-card">
                        <TableRow>
                            <TableHead>Asset</TableHead>
                            <TableHead>Categoria</TableHead>
                            <TableHead className="text-right">Valore</TableHead>
                            <TableHead className="text-right whitespace-nowrap">
                                {previousMonth ? `vs ${formatMonthLong(previousMonth)}` : 'Variazione'}
                            </TableHead>
                            <TableHead className="text-right w-20">Azioni</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {ordered.map((asset) => (
                            <AssetRow key={asset.id} asset={asset} onEdit={onEdit} onViewTransactions={setTxAsset} prices={prices} previousValues={previousValues} readOnly={readOnly} />
                        ))}
                    </TableBody>
                    <TableFooter className="sticky bottom-0 z-10 bg-card">
                        <TableRow className="hover:bg-transparent">
                            <TableCell>
                                <span className="block text-sm font-semibold">Totale</span>
                                <span className="block text-xs font-normal text-muted-foreground">
                                    {assets.length} asset
                                </span>
                            </TableCell>
                            <TableCell className="text-sm font-normal text-muted-foreground">
                                {groups.size} {groups.size === 1 ? 'categoria' : 'categorie'}
                            </TableCell>
                            <TableCell className="text-right font-mono text-base font-bold">
                                <Money value={total} />
                            </TableCell>
                            <TableCell className="text-right font-mono whitespace-nowrap">
                                <DeltaAmount change={totalDelta} className="text-sm" />
                            </TableCell>
                            <TableCell />
                        </TableRow>
                        {/* Net worth only earns its own line when it differs from the
                            month's total — otherwise it would repeat the same figure
                            with no explanation for the repetition. */}
                        {reconciliation.carriedForward.length > 0 && (
                            <TableRow className="hover:bg-transparent">
                                <TableCell colSpan={2}>
                                    <span className="block text-sm font-normal">Patrimonio</span>
                                    <NetWorthReconciliation reconciliation={reconciliation} />
                                </TableCell>
                                <TableCell className="text-right font-mono text-sm">
                                    <Money value={currentNetWorth} />
                                </TableCell>
                                <TableCell colSpan={2} />
                            </TableRow>
                        )}
                    </TableFooter>
                </Table>
            </div>

            {/* Below sm the same assets become cards: five columns cannot be read
                on a phone, and horizontal scrolling through them is worse than
                losing the grid. The comparison month is stated once above the
                list rather than repeated on every card. */}
            {/* No scroller of its own: the page scrolls on a phone, so the list
                runs to full length instead of squeezing into a leftover strip. */}
            <div className="space-y-2 px-4 sm:hidden" data-testid="asset-cards">
                <p className="text-[11px] text-muted-foreground">
                    Variazione {previousMonth ? `vs ${formatMonthLong(previousMonth)}` : 'sul mese precedente'}
                </p>
                {ordered.map((asset) => (
                    <AssetCard key={asset.id} asset={asset} onEdit={onEdit} onViewTransactions={setTxAsset} prices={prices} previousValues={previousValues} readOnly={readOnly} />
                ))}
            </div>

            {/* Now that the page is the scroller, the totals sit at the end of
                the list rather than being pinned — the phone reaches them by
                scrolling, the same as any other row. */}
            <div className="mt-2 space-y-1 border-t bg-card px-4 py-2 sm:hidden" data-testid="asset-cards-summary">
                <div className="flex items-end justify-between gap-2">
                    <div className="min-w-0">
                        <span className="block text-sm font-semibold">Totale</span>
                        <span className="block text-xs text-muted-foreground">
                            {assets.length} asset · {groups.size} {groups.size === 1 ? 'categoria' : 'categorie'}
                        </span>
                    </div>
                    <div className="shrink-0 text-right">
                        <p className="font-mono text-base font-bold">
                            <Money value={total} />
                        </p>
                        <p className="font-mono whitespace-nowrap">
                            <DeltaAmount change={totalDelta} className="text-sm" />
                        </p>
                    </div>
                </div>
                {reconciliation.carriedForward.length > 0 && (
                    <div className="flex items-end justify-between gap-2 border-t pt-1">
                        <div className="min-w-0">
                            <span className="block text-sm">Patrimonio</span>
                            <NetWorthReconciliation reconciliation={reconciliation} />
                        </div>
                        <p className="shrink-0 font-mono text-sm">
                            <Money value={currentNetWorth} />
                        </p>
                    </div>
                )}
            </div>

            <TransactionsDialog asset={txAsset} onClose={() => setTxAsset(null)} />
        </div>
    );
}
