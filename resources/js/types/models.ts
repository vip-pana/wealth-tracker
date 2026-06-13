export interface Category {
    id: number;
    name: string;
    color: string;   // "#3b82f6"
    icon: string | null;
    sort_order: number;
    macro_category: string | null;
    assets_count?: number;
}

export interface Asset {
    id: number;
    category_id: number;
    category: Pick<Category, 'id' | 'name' | 'color' | 'icon' | 'macro_category'>;
    name: string;
    ticker: string | null;
    isin: string | null;
    wallet_address: string | null;
    quantity: number | null;
    price: number | null;   // current price fetched from API
    value: number;          // quantity * price for live assets, manual value otherwise
    synced_at: string | null;  // ISO; set when value came from a bank or broker sync
    sync_source: 'bank' | 'broker' | null;  // which sync stamped synced_at
    bank_linked: boolean;   // true while an active bank connection manages this asset's value
    transaction_managed?: boolean;  // true when shares are derived from a transaction history
    date: string;           // "YYYY-MM-DD"
    notes: string | null;
}

export interface TransactionRow {
    id: number;
    type: 'buy' | 'sell';
    source: 'savings_plan' | 'single' | 'manual' | null;
    shares: number;
    price_per_share: number;
    fees: number | null;
    date: string;           // "YYYY-MM-DD"
    notes: string | null;
}

export interface PositionSummary {
    shares: number;
    average_cost: number;
    cost_basis: number;
    realised_pnl: number;
    current_value: number | null;
    unrealised_pnl: number | null;
    unrealised_pnl_pct: number | null;
}

export interface AssetPriceInfo {
    price: number | null;
    fetched_at: string | null; // ISO string; null if never fetched successfully
}

export interface MonthlySnapshot {
    id: number;
    date: string;    // "YYYY-MM-DD"
    total_value: number;
}

export interface SnapshotCategoryValue {
    id: number;
    snapshot_id: number;
    category_id: number;
    value: number;
}

export interface GoalCategoryAllocation {
    category_id: number | null;
    macro_category: string | null;
    percentage: number;
}

export interface GoalMilestone {
    id: number;
    notes: string | null;
    target_value: number;
    target_date: string; // "YYYY-MM-DD"
}

export interface Goal {
    id: number;
    name: string;
    description: string | null;
    target_value: number;
    target_date: string | null; // "YYYY-MM-DD"
    categoryAllocations: GoalCategoryAllocation[];
    macroAllocations: GoalCategoryAllocation[];
    milestones: GoalMilestone[];
}
