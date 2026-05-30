import { describe, it, expect } from 'vitest';
import {
    allocationSum,
    monthsUntil,
    requiredMonthlyGrowth,
    requiredAnnualGrowth,
} from '@/lib/goalMath';

describe('allocationSum', () => {
    it('sums numeric percentages', () => {
        expect(allocationSum([{ percentage: '40' }, { percentage: '60' }])).toBe(100);
    });

    it('treats non-numeric / empty as 0', () => {
        expect(allocationSum([{ percentage: '' }, { percentage: 'abc' }, { percentage: '25' }])).toBe(25);
    });

    it('is 0 for an empty list', () => {
        expect(allocationSum([])).toBe(0);
    });
});

describe('monthsUntil', () => {
    const now = new Date(2025, 0, 15); // Jan 2025

    it('counts whole months ahead', () => {
        expect(monthsUntil('2025-04-01', now)).toBe(3);
    });

    it('spans years', () => {
        expect(monthsUntil('2027-01-01', now)).toBe(24);
    });

    it('is negative for past dates', () => {
        expect(monthsUntil('2024-10-01', now)).toBe(-3);
    });

    it('is 0 within the same month', () => {
        expect(monthsUntil('2025-01-31', now)).toBe(0);
    });
});

describe('requiredMonthlyGrowth', () => {
    it('returns null when months <= 0', () => {
        expect(requiredMonthlyGrowth(1000, 2000, 0)).toBeNull();
        expect(requiredMonthlyGrowth(1000, 2000, -5)).toBeNull();
    });

    it('returns null when current <= 0', () => {
        expect(requiredMonthlyGrowth(0, 2000, 12)).toBeNull();
        expect(requiredMonthlyGrowth(-100, 2000, 12)).toBeNull();
    });

    it('computes the monthly rate to double in 12 months', () => {
        // 2^(1/12) - 1 ≈ 5.946%
        const r = requiredMonthlyGrowth(1000, 2000, 12);
        expect(r).toBeCloseTo(5.9463, 3);
    });

    it('is 0 when target equals current', () => {
        expect(requiredMonthlyGrowth(1000, 1000, 12)).toBeCloseTo(0, 6);
    });

    it('is negative when target is below current', () => {
        expect(requiredMonthlyGrowth(2000, 1000, 12)).toBeLessThan(0);
    });
});

describe('requiredAnnualGrowth', () => {
    it('returns null when the monthly rate is null', () => {
        expect(requiredAnnualGrowth(1000, 2000, 0)).toBeNull();
        expect(requiredAnnualGrowth(0, 2000, 12)).toBeNull();
    });

    it('annualizes the monthly growth (≈100% to double in a year)', () => {
        const r = requiredAnnualGrowth(1000, 2000, 12);
        expect(r).toBeCloseTo(100, 6);
    });

    it('is 0 when target equals current', () => {
        expect(requiredAnnualGrowth(1000, 1000, 24)).toBeCloseTo(0, 6);
    });
});
