/**
 * Pure goal/growth calculations, extracted from the Goal page so they can be
 * unit-tested in isolation.
 */

/** Sum the `percentage` strings of a list of allocations (non-numeric → 0). */
export function allocationSum(items: { percentage: string }[]): number {
    return items.reduce((s, i) => s + (parseFloat(i.percentage) || 0), 0);
}

/** Whole months from now until the given YYYY-MM-DD date (can be negative). */
export function monthsUntil(dateStr: string, now: Date = new Date()): number {
    const target = new Date(dateStr + 'T00:00:00');
    return (target.getFullYear() - now.getFullYear()) * 12 + (target.getMonth() - now.getMonth());
}

/** Monthly % growth needed to go from `current` to `target` over `months`. */
export function requiredMonthlyGrowth(current: number, target: number, months: number): number | null {
    if (months <= 0 || current <= 0) return null;
    return (Math.pow(target / current, 1 / months) - 1) * 100;
}

/** The annualized equivalent of the required monthly growth. */
export function requiredAnnualGrowth(current: number, target: number, months: number): number | null {
    const monthly = requiredMonthlyGrowth(current, target, months);
    if (monthly === null) return null;
    return (Math.pow(1 + monthly / 100, 12) - 1) * 100;
}

/** Format a percentage: integers without decimals, fractions to one decimal. */
export function formatPct(value: number): string {
    return Number.isInteger(value) ? `${value}%` : `${value.toFixed(1)}%`;
}

/** A value's share of a total, as a percentage. Guards a zero total. */
export function pctOfTotal(value: number, total: number): number {
    return total > 0 ? (value / total) * 100 : 0;
}

/** Deviation of a current share from its target share, in percentage points. */
export function allocationDeviation(currentValue: number, total: number, targetPct: number): number {
    return pctOfTotal(currentValue, total) - targetPct;
}

export interface CapAllocationRow {
    percentage: number;
    /**
     * Optional absolute cap on this category's value at the milestone, in the
     * portfolio's currency (not necessarily euro). null = no cap.
     */
    cap: number | null;
}

/**
 * Apply per-category absolute caps to a milestone's target allocation.
 *
 * A milestone's allocation is in percentages, but a user may want a category to
 * stop tracking its percentage past a point: "keep liquidity at 15%, but never
 * more than 50k", or "Bitcoin never over 100k". At the milestone's target net
 * worth, `pct × target` may exceed a category's cap; when it does, that category
 * is clamped to `cap/target` and the freed percentage is redistributed.
 *
 * Multiple caps are supported. The excess freed by ALL capped categories is
 * spread over the UNCAPPED rows in proportion to their existing weights. If
 * every row is capped (nothing uncapped to absorb it), the excess is left
 * unallocated and the percentages sum to less than 100 — a deliberate signal
 * the caps over-constrain the milestone, not silently forced back to 100.
 *
 * Returns a new percentage per row, in the same order. No-op when there's no
 * target, no cap actually binds, or nothing can absorb the excess. Mirrored in
 * PHP (Goal::applyAllocationCaps) — keep the two in step.
 */
export function applyAllocationCaps(
    rows: CapAllocationRow[],
    targetValue: number | null,
): number[] {
    const original = rows.map((r) => r.percentage);
    if (targetValue === null || targetValue <= 0) return original;

    // The capped share for a bound row is cap/target; a row is "bound" only when
    // that share is below its current percentage (an out-of-reach cap does
    // nothing). Uncapped rows, and capped rows whose cap doesn't bind, keep
    // their percentage and share the freed points.
    let freed = 0;
    let uncappedTotal = 0;
    const capped = rows.map((r) => {
        const boundPct = r.cap !== null && r.cap >= 0 ? (r.cap / targetValue) * 100 : null;
        const binds = boundPct !== null && boundPct < r.percentage;
        if (binds) {
            freed += r.percentage - boundPct;
            return boundPct;
        }
        uncappedTotal += r.percentage;
        return r.percentage;
    });

    if (freed <= 0 || uncappedTotal <= 0) return capped;

    // Spread the freed points over the rows that didn't bind, pro-rata by weight.
    return rows.map((r, i) => {
        const boundPct = r.cap !== null && r.cap >= 0 ? (r.cap / targetValue) * 100 : null;
        const binds = boundPct !== null && boundPct < r.percentage;
        return binds ? capped[i] : capped[i] + (r.percentage / uncappedTotal) * freed;
    });
}
