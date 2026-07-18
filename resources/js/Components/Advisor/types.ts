export type Status = 'pending' | 'done' | 'failed';
export type Kind = 'report' | 'chat' | string;

export interface SessionSummary {
    id: number;
    kind: Kind;
    title: string | null;
    status: Status;
    generating: boolean;
    unread: boolean;
    created_at: string | null;
}

/**
 * Generative-UI widgets a reply can carry. The advisor model chooses to render
 * one by calling the matching tool; the backend attaches app-computed data here
 * (the model never produces this payload). The frontend maps `type` to a React
 * component. New widget types are added to this union as tools gain UI.
 */
export interface PacSimulatorWidget {
    type: 'pac_simulator';
    data: {
        current_net_worth: number;
        target_value: number;
        monthly_amount: number;
        /** Optional: absent on widgets persisted before the step-up feature. */
        annual_increase_pct?: number;
        annual_return: number;
        annual_return_source: string;
        low_confidence: boolean;
    };
}

export interface PositionCardWidget {
    type: 'position_card';
    data:
        | {
              name: string;
              managed: true;
              shares: number;
              average_cost: number;
              cost_basis: number;
              current_value: number | null;
              unrealised_pnl: number | null;
              unrealised_pnl_pct: number | null;
              realised_pnl: number;
          }
        | {
              name: string;
              managed: false;
              current_value: number;
              share_pct: number;
          };
}

export interface AllocationDonutWidget {
    type: 'allocation_donut';
    data: {
        slices: { name: string; value: number; share_pct: number; color: string }[];
        top_category: string;
        top_share_pct: number;
    };
}

export interface NetWorthLineWidget {
    type: 'networth_line';
    data: {
        points: { date: string; total_value: number }[];
        from: string;
        to: string;
    };
}

export interface AllocationVsTargetWidget {
    type: 'allocation_vs_target';
    data: {
        rows: { name: string; current_pct: number; target_pct: number }[];
    };
}

export interface PositionsTableWidget {
    type: 'positions_table';
    data: {
        rows: {
            name: string;
            shares: number;
            average_cost: number;
            current_value: number | null;
            unrealised_pnl: number | null;
            unrealised_pnl_pct: number | null;
        }[];
    };
}

export interface GoalSimulatorWidget {
    type: 'goal_simulator';
    data: {
        current_net_worth: number;
        target_value: number;
        target_date: string;
        months: number;
        annual_return: number;
        annual_return_source: string;
        required_monthly: number;
    };
}

export interface ProfileProposalWidget {
    type: 'profile_proposal';
    data: {
        horizon?: 'short' | 'medium' | 'long';
        risk_tolerance?: 'low' | 'medium' | 'high';
        notes?: string;
    };
}

export type MacroCategory = 'Liquidità' | 'ETF' | 'Cripto';

/**
 * The user's current goal, passed as a page prop. Goal-proposal widgets compare
 * against it to render an already-applied state that survives a page refresh
 * (their local applied state is lost on remount), mirroring how ProfileProposal
 * uses the profile prop. Null when no goal exists yet.
 */
export interface GoalData {
    name: string;
    description: string | null;
    target_value: number;
    target_date: string | null;
    milestones: { notes: string | null; target_value: number; target_date: string }[];
    macro_allocations: { macro_category: MacroCategory; percentage: number }[];
}

export interface GoalCoreProposalWidget {
    type: 'goal_core_proposal';
    data: {
        target_value?: number;
        target_date?: string;
        description?: string;
    };
}

export interface GoalMilestonesProposalWidget {
    type: 'goal_milestones_proposal';
    data: {
        milestones: { label: string | null; target_value: number; target_date: string }[];
    };
}

export interface GoalCompositionProposalWidget {
    type: 'goal_composition_proposal';
    data: {
        buckets: { macro_category: MacroCategory; percentage: number }[];
        rationale: string | null;
        total_pct: number;
    };
}

export interface ProposalOfferWidget {
    type: 'proposal_offer';
    data: {
        kind: 'profile' | 'goal';
    };
}

export type Widget =
    | PacSimulatorWidget
    | PositionCardWidget
    | AllocationDonutWidget
    | NetWorthLineWidget
    | AllocationVsTargetWidget
    | PositionsTableWidget
    | GoalSimulatorWidget
    | ProfileProposalWidget
    | GoalCoreProposalWidget
    | GoalMilestonesProposalWidget
    | GoalCompositionProposalWidget
    | ProposalOfferWidget;

export interface Message {
    id: number;
    role: 'assistant' | 'user';
    content: string;
    status?: Status;
    error?: string | null;
    tool_activity?: string | null;
    widgets?: Widget[] | null;
    created_at: string | null;
}

export interface ActiveSession {
    id: number;
    kind: Kind;
    title: string | null;
    status: Status;
    error: string | null;
    created_at: string | null;
    messages: Message[];
}

// Pool of conversation starters; 3 are drawn per session. Phrased as things to
// understand/evaluate (never "buy X"), matching the advisor's ethical boundary.
export const SUGGESTED_QUESTIONS = [
    'La mia liquidità ferma è troppa?',
    'Quanto sono concentrato e dovrei preoccuparmi?',
    'Come sta andando davvero il mio rendimento?',
    'Il mio portafoglio è coerente col mio profilo di rischio?',
    'Quanto incidono i costi di gestione sul lungo periodo?',
    'Sono in linea con il mio obiettivo?',
    'Il mio PAC è abbastanza per raggiungere l’obiettivo?',
    'Quali sono i rischi principali del mio portafoglio?',
    'Cosa dovrei controllare questo mese?',
    'La mia esposizione a Bitcoin è troppo alta?',
    'Aiutami a definire il mio profilo di rischio',
];

/** Pick `count` distinct questions, varied by the session id so they're stable per session. */
export function pickQuestions(seed: number, count: number): string[] {
    const pool = [...SUGGESTED_QUESTIONS];
    const out: string[] = [];
    let s = seed + 1;
    while (out.length < count && pool.length > 0) {
        s = (s * 1103515245 + 12345) & 0x7fffffff; // deterministic LCG, varies by seed
        out.push(pool.splice(s % pool.length, 1)[0]);
    }
    return out;
}
