import NetWorthLineChart from '@/Components/Charts/NetWorthLineChart';
import { formatDateLabel } from '@/lib/formatters';
import type { NetWorthLineWidget } from '@/Components/Advisor/types';

/**
 * Net-worth-over-time line for the advisor chat, reusing the dashboard's line
 * chart. Shows the full run of snapshots between the two dates the user asked
 * about, so the reply carries a real curve rather than just the two endpoints.
 */
export function NetWorthLine({ data }: { data: NetWorthLineWidget['data'] }) {
    // The height belongs to the chart, not to a wrapper around chart + caption:
    // a fixed-height wrapper leaves the caption outside its own box. The chart's
    // lg:h-full then has the bounded parent it needs.
    return (
        <div className="mt-3">
            <div className="h-60">
                <NetWorthLineChart data={data.points} />
            </div>
            <p className="mt-1 px-1 text-xs text-muted-foreground">
                Periodo: {formatDateLabel(data.from)} – {formatDateLabel(data.to)}
            </p>
        </div>
    );
}
