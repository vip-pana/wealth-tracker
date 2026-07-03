/**
 * Format a number as Euro currency (Italian locale).
 * e.g. 12345.67 → "€ 12.345,67"
 */
export function formatCurrency(value: number): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(value);
}

/**
 * Format a number as Euro currency without decimals.
 * e.g. 12345.67 → "€ 12.346"
 */
export function formatCurrencyNoDecimals(value: number): string {
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

/**
 * Compact currency: € 12.3k or € 1.2M
 */
export function formatCurrencyCompact(value: number): string {
    if (Math.abs(value) >= 1_000_000) {
        return `€ ${(value / 1_000_000).toFixed(1)}M`;
    }
    if (Math.abs(value) >= 1_000) {
        return `€ ${(value / 1_000).toFixed(1)}k`;
    }
    return formatCurrency(value);
}

/**
 * Format a percentage.
 * e.g. 4.23 → "+4.23%" or -1.5 → "-1.50%"
 */
export function formatPercent(value: number): string {
    const sign = value > 0 ? '+' : '';
    return `${sign}${value.toFixed(2)}%`;
}

/**
 * Format a "YYYY-MM-DD" date string to a short month label.
 * e.g. "2025-01-01" → "Gen '25"
 */
export function formatMonthLabel(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('it-IT', { month: 'short', year: '2-digit' });
}

/**
 * Format a "YYYY-MM-DD" date string to a short day label.
 * e.g. "2025-01-15" → "15 gen '25"
 */
export function formatDateLabel(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('it-IT', { day: 'numeric', month: 'short', year: '2-digit' });
}

/**
 * Format a "YYYY-MM-DD" date string to a long day label.
 * e.g. "2025-01-15" → "15 Gennaio 2025"
 */
export function formatDateLong(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('it-IT', { day: 'numeric', month: 'long', year: 'numeric' });
}

/**
 * Format a "YYYY-MM-DD" date string to a long month label.
 * e.g. "2025-01-01" → "Gennaio 2025"
 */
export function formatMonthLong(dateStr: string): string {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });
}

/**
 * Returns the first day of the given month as "YYYY-MM-01".
 * e.g. new Date(2025, 0) → "2025-01-01"
 */
export function toMonthString(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}-01`;
}

/**
 * Returns today's month as "YYYY-MM-01".
 */
export function currentMonth(): string {
    return toMonthString(new Date());
}

/**
 * Steps a "YYYY-MM-DD" month one month forward or back, returning the first day
 * of the resulting month as "YYYY-MM-01". Handles year rollover (Dec↔Jan).
 */
export function stepMonth(month: string, direction: 'prev' | 'next'): string {
    const [year, mon] = month.split('-').map(Number);
    const date = new Date(year, mon - 1 + (direction === 'next' ? 1 : -1), 1);
    return toMonthString(date);
}

/**
 * Returns today's date as "YYYY-MM-DD".
 */
export function today(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}
