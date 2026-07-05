import { describe, expect, it } from 'vitest';
import { PAC_MAX_MONTHS, projectPac, requiredMonthlyContribution } from './pacMath';

describe('projectPac', () => {
    it('reaches an already-met target in zero months', () => {
        const p = projectPac(1000, 1000, 100, 0.05);
        expect(p.months).toBe(0);
        expect(p.balances).toEqual([1000]);
    });

    it('compounds the balance and the monthly contribution', () => {
        // With no growth it is pure accumulation: (target - current) / monthly.
        const p = projectPac(0, 1200, 100, 0);
        expect(p.months).toBe(12);
        expect(p.balances).toHaveLength(13); // month 0 .. month 12
        expect(p.balances[p.balances.length - 1]).toBeGreaterThanOrEqual(1200);
    });

    it('growth shortens the time versus linear accumulation', () => {
        const noGrowth = projectPac(10000, 50000, 500, 0);
        const withGrowth = projectPac(10000, 50000, 500, 0.07);
        expect(withGrowth.months).not.toBeNull();
        expect(noGrowth.months).not.toBeNull();
        expect(withGrowth.months!).toBeLessThan(noGrowth.months!);
    });

    it('returns null months when the goal is unreachable within the cap', () => {
        const p = projectPac(0, 1_000_000, 1, 0);
        expect(p.months).toBeNull();
        expect(p.balances.length).toBe(PAC_MAX_MONTHS + 1);
    });

    it('matches the PHP compound loop for a realistic case', () => {
        // 800€/mo, 5% annual, 100k -> 1M: PHP live gave ~27.6 years (~331 months).
        const p = projectPac(100000, 1_000_000, 800, 0.05);
        expect(p.months).not.toBeNull();
        expect(p.months! / 12).toBeGreaterThan(25);
        expect(p.months! / 12).toBeLessThan(30);
    });
});

describe('requiredMonthlyContribution', () => {
    it('returns 0 when the current balance already reaches the target', () => {
        expect(requiredMonthlyContribution(200000, 100000, 120, 0.05)).toBe(0);
    });

    it('is the inverse of projectPac: the PAC it finds reaches the target in the horizon', () => {
        const months = 240;
        const pac = requiredMonthlyContribution(50000, 500000, months, 0.05);
        expect(pac).toBeGreaterThan(0);
        // Feeding that PAC back into the forward projection reaches the target by
        // the same horizon (allowing the ceil rounding of one month).
        const forward = projectPac(50000, 500000, pac, 0.05);
        expect(forward.months).not.toBeNull();
        expect(forward.months!).toBeLessThanOrEqual(months + 1);
    });

    it('needs a bigger monthly amount for a shorter horizon', () => {
        const short = requiredMonthlyContribution(50000, 500000, 120, 0.05);
        const long = requiredMonthlyContribution(50000, 500000, 360, 0.05);
        expect(short).toBeGreaterThan(long);
    });
});
