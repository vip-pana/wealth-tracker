export interface NetWorthPoint {
    date: string;        // "YYYY-MM-DD"
    total_value: number;
    /** Investable wealth: total minus the emergency-fund buffer. */
    investable?: number;
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

// One month of bank cashflow. `expense` is a negative magnitude, matching the
// signed amounts the rows carry, so `net` is simply income + expense.
export interface MonthlyFlowPoint {
    date: string;
    income: number;
    expense: number;
    net: number;
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

export interface PositionReturn {
    id: number;
    name: string;
    shares: number;
    average_cost: number;
    cost_basis: number;
    current_value: number | null;
    unrealised_pnl: number | null;
    unrealised_pnl_pct: number | null;
    realised_pnl: number;
}

export interface PositionReturns {
    positions: PositionReturn[];
    aggregate: {
        cost_basis: number;
        current_value: number;
        unrealised_pnl: number;
        unrealised_pnl_pct: number | null;
        realised_pnl: number;
    };
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
