export interface NetWorthPoint {
    date: string;        // "YYYY-MM-DD"
    total_value: number;
}

export interface AllocationSlice {
    name: string;
    value: number;
    color: string;
}

export interface StackedBarPoint {
    date: string;
    [categoryName: string]: string | number;
}

export interface GrowthRatePoint {
    date: string;
    change_pct: number;
    total_value: number;
}

export interface MonthComparisonPoint {
    category: string;
    color: string;
    current: number;
    previous: number;
}

export interface ForecastPoint {
    date: string;
    actual: number | null;
    trend: number | null;
    forecast: number | null;
}

export interface MacroAllocationSlice {
    name: string;
    value: number;
}

export interface MacroStackedBarPoint {
    date: string;
    [macroName: string]: string | number;
}

export interface MacroComparisonPoint {
    macro: string;
    current: number;
    previous: number;
}
