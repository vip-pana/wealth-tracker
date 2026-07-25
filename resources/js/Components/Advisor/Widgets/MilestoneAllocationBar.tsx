import { formatPct, applyAllocationCaps } from '@/lib/goalMath';
import { formatCurrencyNoDecimals } from '@/lib/formatters';

type Segment = { category: string; percentage: number; color?: string; cap_amount?: number | null };

/**
 * A milestone's target allocation as a compact horizontal stacked bar. Stacked
 * vertically across the milestones of a goal, the segments make the glide-path
 * (the allocation de-risking toward the objective) readable at a glance — far
 * clearer than the raw "ETF 65% · Cripto 15%" text it replaces. Colours come
 * from the tool (the user's own category colours); a missing one falls back to
 * the same grey the donut uses.
 *
 * When a segment carries a `cap_amount` and `targetValue` is known, the bar
 * shows the EFFECTIVE allocation — the segment clamped to its cap and the excess
 * redistributed to the uncapped ones (same math as the backend). The capped row
 * is annotated with its ceiling so the user sees why it's smaller than its
 * nominal percentage.
 */
export function MilestoneAllocationBar({ segments, targetValue }: { segments: Segment[]; targetValue?: number | null }) {
    if (segments.length === 0) return null;

    const effective = applyAllocationCaps(
        segments.map((s) => ({ percentage: s.percentage, cap: s.cap_amount ?? null })),
        targetValue ?? null,
    );

    return (
        <div className="space-y-1">
            <div className="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                {segments.map((s, i) => (
                    <div
                        key={i}
                        className="h-full"
                        style={{ width: `${Math.max(0, Math.min(100, effective[i]))}%`, backgroundColor: s.color ?? '#94a3b8' }}
                        title={`${s.category} ${formatPct(effective[i])}`}
                    />
                ))}
            </div>
            <div className="flex flex-wrap gap-x-2.5 gap-y-0.5 text-[11px] text-muted-foreground">
                {segments.map((s, i) => (
                    <span key={i} className="inline-flex items-center gap-1">
                        <span className="h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: s.color ?? '#94a3b8' }} />
                        {s.category} {formatPct(effective[i])}
                        {s.cap_amount != null && (
                            <span className="opacity-70">(tetto {formatCurrencyNoDecimals(s.cap_amount)})</span>
                        )}
                    </span>
                ))}
            </div>
        </div>
    );
}
