export interface Category {
    id: number;
    name: string;
    color: string;   // "#3b82f6"
    icon: string | null;
    sort_order: number;
    assets_count?: number;
}

export interface Asset {
    id: number;
    category_id: number;
    category: Pick<Category, 'id' | 'name' | 'color' | 'icon'>;
    name: string;
    value: number;
    date: string;    // "YYYY-MM-DD"
    notes: string | null;
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
