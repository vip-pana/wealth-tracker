import { Money } from '@/Components/ui/Money';
import { DeltaAmount } from '@/Components/Data/DeltaAmount';
import { formatDateLong } from '@/lib/formatters';

export interface SnapshotDiffRow {
    categoryId: number;
    category: string;
    color: string;
    previous: number;
    current: number;
    delta: number;
}

export interface SnapshotDiff {
    snapshotDate: string;
    rows: SnapshotDiffRow[];
    previousTotal: number;
    currentTotal: number;
}

/**
 * What saving a snapshot now would change, category by category, against the
 * most recent existing snapshot — the same reference that makes a month read as
 * "da aggiornare", so this explains that state instead of merely restating the
 * total.
 *
 * Illiquid categories are excluded, matching the rest of the page, so the total
 * here is the liquid figure rather than the whole stored snapshot value.
 */
export function SnapshotDiff({ diff }: { diff: SnapshotDiff }) {
    return (
        <div className="space-y-1 rounded-md border bg-muted/40 p-2 text-sm">
            <p className="text-xs text-muted-foreground">
                Rispetto allo snapshot del {formatDateLong(diff.snapshotDate)}:
            </p>
            {diff.rows.map((row) => (
                <div key={row.categoryId} className="flex items-center justify-between gap-3">
                    <span className="flex min-w-0 items-center gap-1.5">
                        <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: row.color }} />
                        <span className="truncate text-muted-foreground">{row.category}</span>
                    </span>
                    <span className="shrink-0 font-mono text-xs">
                        <DeltaAmount change={{ delta: row.delta, pct: null }} />
                    </span>
                </div>
            ))}
            <div className="flex items-center justify-between gap-3 border-t pt-1 font-medium">
                <span>Totale</span>
                <span className="font-mono">
                    <Money value={diff.currentTotal} />
                </span>
            </div>
        </div>
    );
}
