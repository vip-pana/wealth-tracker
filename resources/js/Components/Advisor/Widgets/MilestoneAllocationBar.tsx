import { formatPct } from '@/lib/goalMath';

type Segment = { category: string; percentage: number; color?: string };

/**
 * A milestone's target allocation as a compact horizontal stacked bar. Stacked
 * vertically across the milestones of a goal, the segments make the glide-path
 * (the allocation de-risking toward the objective) readable at a glance — far
 * clearer than the raw "ETF 65% · Cripto 15%" text it replaces. Colours come
 * from the tool (the user's own category colours); a missing one falls back to
 * the same grey the donut uses.
 */
export function MilestoneAllocationBar({ segments }: { segments: Segment[] }) {
    if (segments.length === 0) return null;

    return (
        <div className="space-y-1">
            <div className="flex h-2 w-full overflow-hidden rounded-full bg-muted">
                {segments.map((s, i) => (
                    <div
                        key={i}
                        className="h-full"
                        style={{ width: `${Math.max(0, Math.min(100, s.percentage))}%`, backgroundColor: s.color ?? '#94a3b8' }}
                        title={`${s.category} ${formatPct(s.percentage)}`}
                    />
                ))}
            </div>
            <div className="flex flex-wrap gap-x-2.5 gap-y-0.5 text-[11px] text-muted-foreground">
                {segments.map((s, i) => (
                    <span key={i} className="inline-flex items-center gap-1">
                        <span className="h-2 w-2 flex-shrink-0 rounded-full" style={{ backgroundColor: s.color ?? '#94a3b8' }} />
                        {s.category} {formatPct(s.percentage)}
                    </span>
                ))}
            </div>
        </div>
    );
}
