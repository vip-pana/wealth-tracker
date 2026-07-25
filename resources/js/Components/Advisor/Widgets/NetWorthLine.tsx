import NetWorthLineChart from '@/Components/Charts/NetWorthLineChart';
import { formatDateLabel } from '@/lib/formatters';
import type { NetWorthLineWidget } from '@/Components/Advisor/types';

/**
 * Net-worth-over-time line for the advisor chat, reusing the dashboard's line
 * chart. Shows the full run of snapshots between the two dates the user asked
 * about, so the reply carries a real curve rather than just the two endpoints.
 */
export function NetWorthLine({ data }: { data: NetWorthLineWidget['data'] }) {
    return (
        <div className="mt-3 h-[240px]">
            <NetWorthLineChart data={data.points} />
            <p className="mt-1 px-1 text-xs text-muted-foreground">
                Periodo: {formatDateLabel(data.from)} – {formatDateLabel(data.to)}
            </p>
        </div>
    );
}
