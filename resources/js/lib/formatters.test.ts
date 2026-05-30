import { describe, it, expect } from 'vitest';
import {
    formatCurrency,
    formatCurrencyNoDecimals,
    formatCurrencyCompact,
    formatPercent,
    formatMonthLabel,
    formatDateLong,
    formatMonthLong,
    toMonthString,
} from '@/lib/formatters';

// Intl currency output uses locale-specific separators and a non-breaking
// space, so assert on normalized content rather than exact glyphs.
const normalize = (s: string) => s.replace(/[\u00a0\u202f]/g, " ");

describe('formatCurrency', () => {
    it('formats with two decimals and euro sign', () => {
        const out = normalize(formatCurrency(12345.67));
        expect(out).toContain('€');
        expect(out).toContain('12.345,67');
    });

    it('handles zero', () => {
        expect(normalize(formatCurrency(0))).toContain('0,00');
    });

    it('handles negatives', () => {
        expect(normalize(formatCurrency(-50))).toContain('-');
    });
});

describe('formatCurrencyNoDecimals', () => {
    it('rounds to whole euros', () => {
        const out = normalize(formatCurrencyNoDecimals(12345.67));
        expect(out).toContain('12.346');
        expect(out).not.toContain(',');
    });
});

describe('formatCurrencyCompact', () => {
    it('uses k for thousands', () => {
        expect(formatCurrencyCompact(12300)).toBe('€ 12.3k');
    });

    it('uses M for millions', () => {
        expect(formatCurrencyCompact(1_200_000)).toBe('€ 1.2M');
    });

    it('falls back to full currency under 1000', () => {
        expect(normalize(formatCurrencyCompact(999))).toContain('999');
    });

    it('handles negative thousands', () => {
        expect(formatCurrencyCompact(-5000)).toBe('€ -5.0k');
    });
});

describe('formatPercent', () => {
    it('prefixes positive values with +', () => {
        expect(formatPercent(4.23)).toBe('+4.23%');
    });

    it('keeps the minus for negatives', () => {
        expect(formatPercent(-1.5)).toBe('-1.50%');
    });

    it('does not prefix zero', () => {
        expect(formatPercent(0)).toBe('0.00%');
    });
});

describe('date labels', () => {
    it('formatMonthLabel shows month + 2-digit year', () => {
        const out = formatMonthLabel('2025-01-15').toLowerCase();
        expect(out).toContain('gen');
        expect(out).toContain('25');
    });

    it('formatMonthLong shows full month + year', () => {
        const out = formatMonthLong('2025-01-15').toLowerCase();
        expect(out).toContain('gennaio');
        expect(out).toContain('2025');
    });

    it('formatDateLong shows day + month + year', () => {
        const out = formatDateLong('2025-01-15').toLowerCase();
        expect(out).toContain('15');
        expect(out).toContain('gennaio');
        expect(out).toContain('2025');
    });

    it('does not drift across timezones (uses local midnight)', () => {
        // The day must stay 15, not roll to 14, regardless of TZ.
        expect(formatDateLong('2025-01-15')).toContain('15');
    });
});

describe('toMonthString', () => {
    it('returns first day of the given month', () => {
        expect(toMonthString(new Date(2025, 0, 23))).toBe('2025-01-01');
    });

    it('zero-pads single-digit months', () => {
        expect(toMonthString(new Date(2025, 8, 1))).toBe('2025-09-01');
    });
});
