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
    wallet_address: string | null;
    quantity: number | null;
    price: number | null;   // current price fetched from API
    value: number;          // quantity * price for live assets, manual value otherwise
    date: string;           // "YYYY-MM-DD"
    notes: string | null;
}

export interface AssetPriceInfo {
    price: number;
    fetched_at: string; // ISO string
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
