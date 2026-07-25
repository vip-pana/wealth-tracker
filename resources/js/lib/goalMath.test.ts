import { describe, it, expect } from 'vitest';
import {
    allocationSum,
    monthsUntil,
    requiredMonthlyGrowth,
    requiredAnnualGrowth,
    formatPct,
    applyAllocationCaps,
    pctOfTotal,
    allocationDeviation,
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

describe('formatPct', () => {
    it('drops decimals for integers', () => {
        expect(formatPct(50)).toBe('50%');
        expect(formatPct(0)).toBe('0%');
    });

    it('keeps one decimal for fractions', () => {
        expect(formatPct(50.5)).toBe('50.5%');
        expect(formatPct(-5.123)).toBe('-5.1%');
    });
});

describe('pctOfTotal', () => {
    it('computes a share', () => {
        expect(pctOfTotal(25, 100)).toBe(25);
        expect(pctOfTotal(1, 3)).toBeCloseTo(33.333, 2);
    });

    it('guards a zero total', () => {
        expect(pctOfTotal(50, 0)).toBe(0);
    });
});

describe('allocationDeviation', () => {
    it('is positive when over-allocated vs target', () => {
        // 50 of 100 = 50%, target 40% -> +10pp.
        expect(allocationDeviation(50, 100, 40)).toBeCloseTo(10);
    });

    it('is negative when under-allocated vs target', () => {
        expect(allocationDeviation(30, 100, 40)).toBeCloseTo(-10);
    });

    it('treats a zero total as 0% current allocation', () => {
        expect(allocationDeviation(0, 0, 40)).toBeCloseTo(-40);
    });
});

describe('applyAllocationCaps', () => {
    // Azioni 50, Liquidità 15 (cap 50k), Bitcoin 25, Oro 5, Obblig 5.
    const rows = [
        { percentage: 50, cap: null },
        { percentage: 15, cap: 50_000 },
        { percentage: 25, cap: null },
        { percentage: 5, cap: null },
        { percentage: 5, cap: null },
    ];

    it('is a no-op when no cap is set', () => {
        const none = rows.map((r) => ({ ...r, cap: null }));
        expect(applyAllocationCaps(none, 1_000_000)).toEqual([50, 15, 25, 5, 5]);
    });

    it('is a no-op when the cap is not yet binding', () => {
        // 15% of 250k = 37.5k ≤ 50k cap → unchanged.
        expect(applyAllocationCaps(rows, 250_000)).toEqual([50, 15, 25, 5, 5]);
    });

    it('clamps a capped row and spreads the excess pro-rata over uncapped rows', () => {
        // 15% of 1M = 150k > 50k. Capped → 5%; freed 10pp over the 85pp of
        // uncapped rows in proportion to their weights.
        const out = applyAllocationCaps(rows, 1_000_000);
        expect(out[1]).toBeCloseTo(5); // liquidity → cap/target = 5%
        expect(out[0]).toBeCloseTo(50 + (50 / 85) * 10); // Azioni
        expect(out[2]).toBeCloseTo(25 + (25 / 85) * 10); // Bitcoin
        expect(out.reduce((s, v) => s + v, 0)).toBeCloseTo(100);
    });

    it('supports multiple caps, spreading the excess only over uncapped rows', () => {
        // Liquidità 15% cap 50k AND Bitcoin 25% cap 100k, at 1M.
        // liq → 5% (freed 10), btc → 10% (freed 15) → 25pp over Azioni+Oro+Obblig
        // (uncapped total 60pp).
        const multi = [
            { percentage: 50, cap: null }, // Azioni
            { percentage: 15, cap: 50_000 }, // Liquidità → 5%
            { percentage: 25, cap: 100_000 }, // Bitcoin → 10%
            { percentage: 5, cap: null }, // Oro
            { percentage: 5, cap: null }, // Obblig
        ];
        const out = applyAllocationCaps(multi, 1_000_000);
        expect(out[1]).toBeCloseTo(5);
        expect(out[2]).toBeCloseTo(10);
        expect(out[0]).toBeCloseTo(50 + (50 / 60) * 25); // Azioni
        expect(out[3]).toBeCloseTo(5 + (5 / 60) * 25); // Oro
        expect(out.reduce((s, v) => s + v, 0)).toBeCloseTo(100);
    });

    it('leaves the excess unallocated (sum < 100) when every row is capped', () => {
        // Both rows capped and binding, nothing uncapped to absorb the excess.
        const allCapped = [
            { percentage: 60, cap: 100_000 }, // → 10%
            { percentage: 40, cap: 200_000 }, // → 20%
        ];
        const out = applyAllocationCaps(allCapped, 1_000_000);
        expect(out[0]).toBeCloseTo(10);
        expect(out[1]).toBeCloseTo(20);
        expect(out.reduce((s, v) => s + v, 0)).toBeCloseTo(30); // deliberately < 100
    });

    it('ignores an out-of-reach cap (does not raise the percentage)', () => {
        // Cap 500k on a 15% row at 1M = 150k < 500k → cap never binds → no-op.
        const loose = [
            { percentage: 85, cap: null },
            { percentage: 15, cap: 500_000 },
        ];
        expect(applyAllocationCaps(loose, 1_000_000)).toEqual([85, 15]);
    });
});
