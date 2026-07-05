import { PacSimulator } from '@/Components/Advisor/Widgets/PacSimulator';
import { PositionCard } from '@/Components/Advisor/Widgets/PositionCard';
import { AllocationDonut } from '@/Components/Advisor/Widgets/AllocationDonut';
import { NetWorthLine } from '@/Components/Advisor/Widgets/NetWorthLine';
import type { Widget } from '@/Components/Advisor/types';

/**
 * Renders the generative-UI widgets an assistant reply carries. Maps each
 * widget's `type` to its component; an unknown type (e.g. an older client
 * meeting a newer widget) is skipped rather than crashing the message.
 */
export function AdvisorWidgets({ widgets }: { widgets: Widget[] }) {
    return (
        <>
            {widgets.map((widget, i) => {
                switch (widget.type) {
                    case 'pac_simulator':
                        return <PacSimulator key={i} data={widget.data} />;
                    case 'position_card':
                        return <PositionCard key={i} data={widget.data} />;
                    case 'allocation_donut':
                        return <AllocationDonut key={i} data={widget.data} />;
                    case 'networth_line':
                        return <NetWorthLine key={i} data={widget.data} />;
                    default:
                        return null;
                }
            })}
        </>
    );
}
