/**
 * Pure dashboard/chart calculations, extracted from page and chart components
 * so they can be unit-tested without rendering.
 */

/**
 * Percentage change between two net-worth points. Returns null when there is no
 * usable baseline (missing points, or a previous value of zero).
 */
export function netWorthChangePct(
    previous: number | null | undefined,
    last: number | null | undefined,
): number | null {
    if (previous == null || last == null || previous === 0) return null;
    return ((last - previous) / previous) * 100;
}

/**
 * The date where a forecast series transitions from historical to projected,
 * i.e. the first point that is forecast-only. Null if there is no such split.
 */
export function findForecastSplitDate(
    data: { date: string; actual: number | null; forecast: number | null }[],
): string | null {
    const splitIndex = data.findIndex((d) => d.forecast !== null && d.actual === null);
    return splitIndex > 0 ? (data[splitIndex]?.date ?? null) : null;
}
