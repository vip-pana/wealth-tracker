export const MACRO_COLORS: Record<string, string> = {
    'Liquidità': '#60a5fa',
    'ETF': '#34d399',
    'Cripto': '#f59e0b',
};

export interface CurrentAllocationItem {
    category_id: number;
    value: number;
}

export interface CurrentMacroAllocationItem {
    macro_category: string;
    value: number;
}

export interface AllocationFormItem {
    category_id?: string;
    macro_category?: string;
    percentage: string;
    /**
     * Optional absolute cap on this category at the milestone, in the portfolio's
     * currency. Empty string = no cap. When set, the category stops tracking its
     * percentage once `percentage × milestone target` would exceed it.
     */
    cap_amount?: string;
}

export interface MilestoneFormItem {
    notes: string;
    action: string;
    rationale: string;
    target_value: string;
    target_date: string;
    allocation: AllocationFormItem[];
}

export type GoalFormData = {
    name: string;
    description: string;
    target_value: string;
    target_date: string;
    milestones: MilestoneFormItem[];
};
