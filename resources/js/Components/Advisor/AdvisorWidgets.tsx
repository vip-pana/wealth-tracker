import { PacSimulator } from '@/Components/Advisor/Widgets/PacSimulator';
import { PositionCard } from '@/Components/Advisor/Widgets/PositionCard';
import { AllocationDonut } from '@/Components/Advisor/Widgets/AllocationDonut';
import { NetWorthLine } from '@/Components/Advisor/Widgets/NetWorthLine';
import { AllocationVsTarget } from '@/Components/Advisor/Widgets/AllocationVsTarget';
import { PositionsTable } from '@/Components/Advisor/Widgets/PositionsTable';
import { GoalSimulator } from '@/Components/Advisor/Widgets/GoalSimulator';
import { ProfileProposal } from '@/Components/Advisor/Widgets/ProfileProposal';
import { GoalCoreProposal } from '@/Components/Advisor/Widgets/GoalCoreProposal';
import { GoalMilestonesProposal } from '@/Components/Advisor/Widgets/GoalMilestonesProposal';
import { GoalCompositionProposal } from '@/Components/Advisor/Widgets/GoalCompositionProposal';
import { ProposalOffer } from '@/Components/Advisor/Widgets/ProposalOffer';
import { type InvestorProfile } from '@/Components/Advisor/ProfileDialog';
import type { Widget, GoalData } from '@/Components/Advisor/types';

/**
 * Renders the generative-UI widgets an assistant reply carries. Maps each
 * widget's `type` to its component; an unknown type (e.g. an older client
 * meeting a newer widget) is skipped rather than crashing the message.
 */
export function AdvisorWidgets({ widgets, profile, goal, isLast, onPropose }: { widgets: Widget[]; profile?: InvestorProfile | null; goal?: GoalData | null; isLast?: boolean; onPropose?: (kind: 'profile' | 'goal') => void }) {
    return (
        <>
            {widgets.map((widget, i) => {
                // The "generate the proposal" button (proposal_offer) is only
                // meaningful on the latest turn: once the user keeps talking, that
                // offer is superseded (a fresh button is surfaced at the bottom of
                // the chat), so an old one buried up the thread is just confusing.
                // Actual proposal cards stay — they carry content worth scrolling to.
                if (widget.type === 'proposal_offer' && isLast === false) {
                    return null;
                }
                switch (widget.type) {
                    case 'pac_simulator':
                        return <PacSimulator key={i} data={widget.data} />;
                    case 'position_card':
                        return <PositionCard key={i} data={widget.data} />;
                    case 'allocation_donut':
                        return <AllocationDonut key={i} data={widget.data} />;
                    case 'networth_line':
                        return <NetWorthLine key={i} data={widget.data} />;
                    case 'allocation_vs_target':
                        return <AllocationVsTarget key={i} data={widget.data} />;
                    case 'positions_table':
                        return <PositionsTable key={i} data={widget.data} />;
                    case 'goal_simulator':
                        return <GoalSimulator key={i} data={widget.data} />;
                    case 'profile_proposal':
                        return <ProfileProposal key={i} data={widget.data} profile={profile} />;
                    case 'goal_core_proposal':
                        return <GoalCoreProposal key={i} data={widget.data} goal={goal} />;
                    case 'goal_milestones_proposal':
                        return <GoalMilestonesProposal key={i} data={widget.data} goal={goal} />;
                    case 'goal_composition_proposal':
                        return <GoalCompositionProposal key={i} data={widget.data} goal={goal} />;
                    case 'proposal_offer':
                        return <ProposalOffer key={i} data={widget.data} onPropose={onPropose} />;
                    default:
                        return null;
                }
            })}
        </>
    );
}
