import { describe, expect, it } from 'vitest';
import { netWorthChangePct, findForecastSplitDate, priceFreshness, bankFreshness, brokerFreshness } from './metrics';

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

describe('priceFreshness', () => {
    const now = new Date('2026-05-30T12:00:00Z');
    const ago = (ms: number) => new Date(now.getTime() - ms).toISOString();

    it('reports a missing price as stale', () => {
        expect(priceFreshness(null, now)).toEqual({ label: 'prezzo non disponibile', stale: true });
        expect(priceFreshness('', now)).toEqual({ label: 'prezzo non disponibile', stale: true });
    });

    it('reports an unparseable timestamp as stale', () => {
        expect(priceFreshness('not-a-date', now).stale).toBe(true);
    });

    it('labels a very recent price as "ora" and not stale', () => {
        const r = priceFreshness(ago(30 * 1000), now);
        expect(r.label).toBe('agg. ora');
        expect(r.stale).toBe(false);
    });

    it('labels minutes and hours', () => {
        expect(priceFreshness(ago(5 * 60_000), now).label).toBe('agg. 5 min fa');
        expect(priceFreshness(ago(60 * 60_000), now).label).toBe('agg. 1 ora fa');
        expect(priceFreshness(ago(3 * 60 * 60_000), now).label).toBe('agg. 3 ore fa');
    });

    it('is fresh just under 24h and stale at/after 24h', () => {
        expect(priceFreshness(ago(23 * 60 * 60_000), now).stale).toBe(false);
        const oneDay = priceFreshness(ago(24 * 60 * 60_000), now);
        expect(oneDay.stale).toBe(true);
        expect(oneDay.label).toBe('agg. 1 giorno fa');
        expect(priceFreshness(ago(3 * 24 * 60 * 60_000), now).label).toBe('agg. 3 giorni fa');
    });
});

describe('bankFreshness', () => {
    const now = new Date('2026-05-30T12:00:00Z');
    const ago = (ms: number) => new Date(now.getTime() - ms).toISOString();

    it('reports a not-yet-synced balance distinctly from a missing price', () => {
        expect(bankFreshness(null, now)).toEqual({ label: 'in attesa di sincronizzazione', stale: true });
    });

    it('tolerates a 1–2 day old balance, unlike a market price', () => {
        // Same age that a price would already flag as stale at 24h.
        expect(bankFreshness(ago(2 * 24 * 60 * 60_000), now).stale).toBe(false);
        expect(bankFreshness(ago(2 * 24 * 60 * 60_000), now).label).toBe('agg. 2 giorni fa');
    });

    it('flags a balance older than four days', () => {
        expect(bankFreshness(ago(4 * 24 * 60 * 60_000), now).stale).toBe(true);
    });
});

describe('brokerFreshness', () => {
    const now = new Date('2026-05-30T12:00:00Z');
    const ago = (ms: number) => new Date(now.getTime() - ms).toISOString();

    it('reports a not-yet-synced balance', () => {
        expect(brokerFreshness(null, now)).toEqual({ label: 'in attesa di sincronizzazione', stale: true });
    });

    it('stays fresh within a day', () => {
        expect(brokerFreshness(ago(12 * 60 * 60_000), now).stale).toBe(false);
    });

    it('goes stale sooner than a bank balance — past two days', () => {
        // Not stale at 4 days for a bank, but a broker sync flags it at two.
        expect(brokerFreshness(ago(2 * 24 * 60 * 60_000), now).stale).toBe(true);
        expect(bankFreshness(ago(2 * 24 * 60 * 60_000), now).stale).toBe(false);
    });
});
