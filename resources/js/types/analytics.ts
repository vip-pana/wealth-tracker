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

export interface AllocationShare {
    name: string;
    value: number;
    share_pct: number;
}

export interface AllocationDriftRow {
    name: string;
    share_pct: number;
    share_pct_then: number;
    delta_pp: number;
}

export interface GoalEta {
    reached: boolean;
    target_value: number;
    avg_monthly_gain?: number;
    months_to_goal?: number;
    projected_date?: string;
    target_date?: string | null;
    on_track?: boolean | null;
    low_confidence?: boolean;
}

export type PortfolioMetrics =
    | { hasData: false }
    | {
          hasData: true;
          monthsTracked: number;
          totalNetWorth: number;
          allocation: AllocationShare[];
          allocationDrift: AllocationDriftRow[];
          concentration: { hhi: number; top_category: string; top_share_pct: number };
          liquidity: { value: number; share_pct: number };
          volatility: {
              monthly_stddev_pct: number | null;
              best_month_pct: number | null;
              worst_month_pct: number | null;
          };
          goalEta: GoalEta | null;
      };
