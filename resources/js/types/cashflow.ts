export type FlowType = 'income' | 'expense' | 'transfer';

export interface Account {
    id: number;
    label: string;
    last_sync_at: string | null;
    last_sync_status: string | null;
    last_sync_error: string | null;
}

export interface Transaction {
    id: number;
    account_id: number;
    date: string;
    amount: number;
    description: string;
    flow_type: FlowType | null;
    excluded: boolean;
    is_manual: boolean;
    // Whether the user has been through this row. The review dialog hides the
    // reviewed ones by default; the month's totals count them regardless.
    reviewed: boolean;
    is_salary: boolean;
}

// A staged, unsaved change to a row. The page keeps these in a Map keyed by
// transaction id so the month's totals can react before anything is persisted.
export interface Edit {
    flow_type: FlowType;
    excluded: boolean;
}
