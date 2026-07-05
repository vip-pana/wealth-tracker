/**
 * PAC (monthly-contribution) compound projection, mirrored from the PHP that
 * powers the advisor's simulate_pac tool (AdvisorToolFactory::describePacSimulation
 * / emitPacWidget). Kept in TS so the interactive PacSimulator widget can re-run
 * the same maths as the user drags the sliders, without another model round-trip.
 * Both sides are unit-tested; keep them in step.
 */

/** Hard stop for the search: 100 years, matching the PHP `maxMonths`. */
export const PAC_MAX_MONTHS = 1200;

export interface PacProjection {
    /** Whole months to reach the target, or null when unreachable within the cap. */
    months: number | null;
    /** Balance at each elapsed month, starting from month 0 = current net worth. */
    balances: number[];
}

/**
 * Iterate month by month: the balance grows at the assumed monthly rate and the
 * PAC is added and itself compounds, until it reaches the target (or the cap).
 * A linear `remaining / monthly` ignores compounding and yields absurd century
 * ETAs on long-horizon goals, so we compound — same loop as the PHP.
 */
export function projectPac(
    currentNetWorth: number,
    target: number,
    monthlyAmount: number,
    annualReturn: number,
): PacProjection {
    const monthlyRate = Math.pow(1 + annualReturn, 1 / 12) - 1;
    const balances: number[] = [currentNetWorth];

    let balance = currentNetWorth;
    let months = 0;
    while (balance < target && months < PAC_MAX_MONTHS) {
        balance = balance * (1 + monthlyRate) + monthlyAmount;
        months++;
        balances.push(balance);
    }

    return {
        months: months >= PAC_MAX_MONTHS ? null : months,
        balances,
    };
}

/**
 * The monthly contribution needed to grow `current` to `target` over `months`
 * at a given annual rate — the inverse of projectPac (which fixes the PAC and
 * finds the time). Closed-form annuity, mirrored from the PHP
 * requiredMonthlyContribution. Returns 0 when the current balance alone already
 * reaches the target within the horizon. Powers the interactive goal simulator.
 */
export function requiredMonthlyContribution(
    current: number,
    target: number,
    months: number,
    annualReturn: number,
): number {
    if (months < 1) return 0;
    const i = Math.pow(1 + annualReturn, 1 / 12) - 1;
    const growth = Math.pow(1 + i, months);
    const futureOfCurrent = current * growth;
    if (futureOfCurrent >= target) return 0;
    const annuityFactor = i > 1e-9 ? (growth - 1) / i : months;
    return (target - futureOfCurrent) / annuityFactor;
}
