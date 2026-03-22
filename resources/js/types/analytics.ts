export interface NetWorthPoint {
    month: string;       // "YYYY-MM-DD"
    total_value: number;
}

export interface AllocationSlice {
    name: string;
    value: number;
    color: string;
}

export interface StackedBarPoint {
    month: string;
    [categoryName: string]: string | number;
}

export interface GrowthRatePoint {
    month: string;
    mom_pct: number;
    total_value: number;
}

export interface MonthComparisonPoint {
    category: string;
    color: string;
    current: number;
    previous: number;
}

export interface ForecastPoint {
    month: string;
    actual: number | null;
    trend: number | null;
    forecast: number | null;
}

export interface MacroAllocationSlice {
    name: string;
    value: number;
}

export interface MacroStackedBarPoint {
    month: string;
    [macroName: string]: string | number;
}

export interface MacroComparisonPoint {
    macro: string;
    current: number;
    previous: number;
}
