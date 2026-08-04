import { Money } from '@/Components/ui/Money';
import { formatMonthLong } from '@/lib/formatters';
import { monthsSince } from '@/lib/metrics';

export interface CarriedForward {
    categoryId: number;
    category: string;
    color: string;
    value: number;
    asOf: string;
}

export interface Reconciliation {
    total: number;
    currentMonthTotal: number;
    carriedForwardTotal: number;
    carriedForward: CarriedForward[];
}

/**
 * Why net worth exceeds the month's asset total: some categories have no row
 * this month and count at their last known value.
 *
 * This used to itemise every carried-forward category as a small ledger. The
 * itemisation now lives in the page's per-category cards, where a missing
 * category shows up next to the ones that are up to date — so all that is left
 * here is the headline: how much of the total is stale, and how stale.
 */
export function NetWorthReconciliation({ reconciliation }: { reconciliation: Reconciliation }) {
    const { carriedForward, carriedForwardTotal } = reconciliation;

    if (carriedForward.length === 0) {
        return null;
    }

    // Lead with the worst offender: a category eleven months out of date is a
    // different problem from one that is a month behind.
    const oldest = carriedForward.reduce((a, b) => (a.asOf < b.asOf ? a : b));
    const staleMonths = monthsSince(oldest.asOf);

    return (
        <p className={staleMonths >= 3 ? 'text-xs text-red-500' : 'text-xs text-amber-500'}>
            <Money value={carriedForwardTotal} /> da{' '}
            {carriedForward.length === 1
                ? <>{oldest.category}, ferma a {formatMonthLong(oldest.asOf)}</>
                : <>{carriedForward.length} categorie non aggiornate, la più vecchia da {formatMonthLong(oldest.asOf)}</>}
        </p>
    );
}

/**
 * The same reconciliation itemised line by line. Too heavy to sit on the page
 * permanently — that is what the one-line summary above is for — but exactly
 * what is wanted in the snapshot dialog, where the user is about to freeze this
 * number and needs to see what it is made of.
 */
export function NetWorthBreakdown({ reconciliation, month }: { reconciliation: Reconciliation; month: string }) {
    const { carriedForward, currentMonthTotal, total } = reconciliation;

    if (carriedForward.length === 0) {
        return null;
    }

    return (
        <div className="space-y-1 rounded-md border bg-muted/40 p-2 text-sm">
            <div className="flex items-center justify-between gap-3">
                <span className="text-muted-foreground">Asset di {formatMonthLong(month)}</span>
                <span className="font-mono"><Money value={currentMonthTotal} /></span>
            </div>
            {carriedForward.map((item) => (
                <div key={item.categoryId} className="flex items-center justify-between gap-3">
                    <span className="flex min-w-0 items-center gap-1.5 text-muted-foreground">
                        <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: item.color }} />
                        <span className="truncate">{item.category}</span>
                        <span className={monthsSince(item.asOf) >= 3 ? 'shrink-0 text-red-500' : 'shrink-0 text-amber-500'}>
                            · da {formatMonthLong(item.asOf)}
                        </span>
                    </span>
                    <span className="font-mono"><Money value={item.value} /></span>
                </div>
            ))}
            <div className="flex items-center justify-between gap-3 border-t pt-1 font-medium">
                <span>Totale patrimonio</span>
                <span className="font-mono"><Money value={total} /></span>
            </div>
        </div>
    );
}
