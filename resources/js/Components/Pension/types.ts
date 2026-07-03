export interface PensionCategory {
    id: number;
    name: string;
    color: string;
    macro_category: string | null;
}

export interface PensionEntry {
    id: number;
    name: string;
    value: number;
    year: number;
    date: string;
    notes: string | null;
    category_id: number;
    category: {
        id: number;
        name: string;
        color: string;
    };
}
