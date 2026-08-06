import { formatPct, applyAllocationCaps } from '@/lib/goalMath';
import { formatCurrencyNoDecimals } from '@/lib/formatters';
import { cn } from '@/lib/utils';

export type Segment = { category: string; percentage: number; color?: string; cap_amount?: number | null };

const FALLBACK_COLOR = '#94a3b8';

/** Effective shares — a binding cap clamps its row and frees points to the rest. */
function effectiveShares(segments: Segment[], targetValue: number): number[] {
    return applyAllocationCaps(
        segments.map((s) => ({ percentage: s.percentage, cap: s.cap_amount ?? null })),
        targetValue,
    );
}

/**
 * A milestone's target allocation as one horizontal bar per category, with the
 * change in percentage points against the previous milestone. The milestones of
 * a goal form a glide path (equities down, bonds up as the target nears), so the
 * delta is the point: it says which way the mix is moving, which a single
 * part-to-whole snapshot can't.
 *
 * A category sitting at 0% keeps its row — "no bonds yet" is information in a
 * glide path, not noise.
 *
 * Deltas compare EFFECTIVE shares (post-cap), not the nominal percentages:
 * with a binding cap the nominal figures would report a move that doesn't
 * happen. The compact stacked form for advisor chat bubbles stays in
 * MilestoneAllocationBar — this one is for the roomier goal card.
 */
export function MilestoneAllocationDetail({
    segments,
    targetValue,
    previous,
    previousTargetValue,
}: {
    segments: Segment[];
    targetValue: number;
    previous?: Segment[] | null;
    // The previous step's own target value: a cap is an absolute amount, so its
    // effective share depends on the target it is measured against.
    previousTargetValue?: number | null;
}) {
    if (segments.length === 0) return null;

    const effective = effectiveShares(segments, targetValue);

    // Previous shares keyed by category, so a reordered or resized list still
    // lines up.
    const previousByCategory = new Map<string, number>();
    if (previous && previous.length > 0) {
        const previousEffective = effectiveShares(previous, previousTargetValue ?? targetValue);
        previous.forEach((s, i) => previousByCategory.set(s.category, previousEffective[i]));
    }

    const rows = segments
        .map((segment, i) => {
            const share = effective[i];
            const before = previousByCategory.get(segment.category);
            return {
                segment,
                share,
                delta: before === undefined ? null : share - before,
            };
        })
        .sort((a, b) => b.share - a.share);

    // The first milestone has nothing to compare against, so every delta is
    // null. Reserving the column anyway leaves a permanently empty gutter that
    // stops the bars halfway across a phone.
    const hasDeltas = rows.some(({ delta }) => delta !== null);

    return (
        <div className="space-y-3">
            {rows.map(({ segment, share, delta }) => (
                // The label and the two figures are fixed-width, which on a phone
                // leaves the bar almost nothing. Below sm the name takes its own
                // line; sm:contents then flattens the wrapper back so the row is
                // the original four-child flex, unchanged.
                <div key={segment.category} className="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                    <span className="w-full shrink-0 truncate text-sm sm:w-28">{segment.category}</span>

                    <div className="flex w-full items-center gap-2 sm:contents">
                        <div className="h-3 flex-1 overflow-hidden rounded-full bg-muted">
                            <div
                                className="h-full rounded-r-full"
                                style={{
                                    width: `${Math.max(0, Math.min(100, share))}%`,
                                    backgroundColor: segment.color ?? FALLBACK_COLOR,
                                }}
                            />
                        </div>

                        <span className="w-12 shrink-0 text-right font-mono text-sm tabular-nums">{formatPct(share)}</span>

                        {/* Which way the mix is moving vs the previous step. */}
                        {hasDeltas && (
                            <span
                                className={cn(
                                    'w-14 shrink-0 text-right font-mono text-xs tabular-nums sm:w-16',
                                    delta === null || Math.abs(delta) < 0.05
                                        ? 'text-muted-foreground'
                                        : delta > 0
                                          ? 'text-blue-400'
                                          : 'text-orange-400',
                                )}
                            >
                                {delta === null ? '' : Math.abs(delta) < 0.05 ? '—' : `${delta > 0 ? '▲+' : '▼−'}${formatPct(Math.abs(delta))}`}
                            </span>
                        )}
                    </div>
                </div>
            ))}

            {rows.some(({ segment }) => segment.cap_amount != null) && (
                <div className="flex flex-wrap gap-x-3 gap-y-0.5 pt-1 text-xs text-muted-foreground">
                    {rows
                        .filter(({ segment }) => segment.cap_amount != null)
                        .map(({ segment }) => (
                            <span key={segment.category}>
                                {segment.category}: tetto {formatCurrencyNoDecimals(segment.cap_amount as number)}
                            </span>
                        ))}
                </div>
            )}
        </div>
    );
}
