import AllocationDonutChart from '@/Components/Charts/AllocationDonutChart';
import type { AllocationDonutWidget } from '@/Components/Advisor/types';

/**
 * Allocation donut for the advisor chat, reusing the dashboard's donut chart.
 * The tool supplies each slice's category colour, so the widget matches the
 * colours the user already sees elsewhere.
 */
export function AllocationDonut({ data }: { data: AllocationDonutWidget['data'] }) {
    return (
        <div className="mt-3">
            <AllocationDonutChart data={data.slices.map((s) => ({ name: s.name, value: s.value, color: s.color }))} />
            <p className="mt-1 px-1 text-xs text-muted-foreground">
                Più pesante: {data.top_category} ({data.top_share_pct.toFixed(1)}%)
            </p>
        </div>
    );
}
