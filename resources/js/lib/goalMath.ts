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
