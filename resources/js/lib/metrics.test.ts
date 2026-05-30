import { describe, expect, it } from 'vitest';
import { netWorthChangePct, findForecastSplitDate } from './metrics';

describe('netWorthChangePct', () => {
    it('computes a positive change', () => {
        expect(netWorthChangePct(100, 120)).toBeCloseTo(20);
    });

    it('computes a negative change', () => {
        expect(netWorthChangePct(100, 80)).toBeCloseTo(-20);
    });

    it('is zero when unchanged', () => {
        expect(netWorthChangePct(100, 100)).toBe(0);
    });

    it('returns null when the baseline is zero (no division)', () => {
        expect(netWorthChangePct(0, 100)).toBeNull();
    });

    it('returns null when a point is missing', () => {
        expect(netWorthChangePct(undefined, 100)).toBeNull();
        expect(netWorthChangePct(100, null)).toBeNull();
    });
});

describe('findForecastSplitDate', () => {
    const actual = (date: string, v: number) => ({ date, actual: v, forecast: null });
    const forecast = (date: string, v: number) => ({ date, actual: null, forecast: v });

    it('returns the first forecast-only point after history', () => {
        const data = [actual('2026-01-01', 100), actual('2026-02-01', 110), forecast('2026-03-01', 120)];
        expect(findForecastSplitDate(data)).toBe('2026-03-01');
    });

    it('returns null when there is no forecast', () => {
        expect(findForecastSplitDate([actual('2026-01-01', 100), actual('2026-02-01', 110)])).toBeNull();
    });

    it('returns null when the series is entirely forecast (no history to split from)', () => {
        // splitIndex would be 0, which is not a real split.
        expect(findForecastSplitDate([forecast('2026-03-01', 120)])).toBeNull();
    });

    it('returns null for an empty series', () => {
        expect(findForecastSplitDate([])).toBeNull();
    });
});
