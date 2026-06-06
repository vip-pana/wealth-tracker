/**
 * Pure dashboard/chart calculations, extracted from page and chart components
 * so they can be unit-tested without rendering.
 */

/**
 * Percentage change between two net-worth points. Returns null when there is no
 * usable baseline (missing points, or a previous value of zero).
 */
export function netWorthChangePct(
    previous: number | null | undefined,
    last: number | null | undefined,
): number | null {
    if (previous == null || last == null || previous === 0) return null;
    return ((last - previous) / previous) * 100;
}

/**
 * The date where a forecast series transitions from historical to projected,
 * i.e. the first point that is forecast-only. Null if there is no such split.
 */
export function findForecastSplitDate(
    data: { date: string; actual: number | null; forecast: number | null }[],
): string | null {
    const splitIndex = data.findIndex((d) => d.forecast !== null && d.actual === null);
    return splitIndex > 0 ? (data[splitIndex]?.date ?? null) : null;
}

/** A live price is considered stale once it is older than this. */
export const PRICE_STALE_AFTER_MS = 24 * 60 * 60 * 1000;

export interface PriceFreshness {
    /** Human label of the price age, in Italian (e.g. "2 min fa", "3 giorni fa"). */
    label: string;
    /** True when the price is missing or older than PRICE_STALE_AFTER_MS. */
    stale: boolean;
}

/**
 * Describe how fresh a live price is, given when it was fetched. A null/empty
 * timestamp (price never fetched) is reported as missing and stale.
 */
export function priceFreshness(
    fetchedAt: string | null | undefined,
    now: Date = new Date(),
): PriceFreshness {
    if (!fetchedAt) {
        return { label: 'prezzo non disponibile', stale: true };
    }

    const fetched = new Date(fetchedAt);
    if (Number.isNaN(fetched.getTime())) {
        return { label: 'prezzo non disponibile', stale: true };
    }

    const ageMs = now.getTime() - fetched.getTime();
    const stale = ageMs >= PRICE_STALE_AFTER_MS;

    return { label: 'agg. ' + relativeAge(ageMs), stale };
}

/**
 * A bank balance is considered stale later than a market price: Enable Banking
 * consent lasts ~90 days and balances are read roughly daily, so a 1–2 day old
 * balance is normal. Only flag it past this window.
 */
export const BANK_BALANCE_STALE_AFTER_MS = 4 * 24 * 60 * 60 * 1000;

/**
 * Describe how fresh a bank balance is. A null timestamp means the account is
 * linked but not yet synced (reported as such, and treated as stale so the UI
 * nudges a refresh). Unlike priceFreshness this never says "prezzo".
 */
export function bankFreshness(
    syncedAt: string | null | undefined,
    now: Date = new Date(),
): PriceFreshness {
    if (!syncedAt) {
        return { label: 'in attesa di sincronizzazione', stale: true };
    }

    const synced = new Date(syncedAt);
    if (Number.isNaN(synced.getTime())) {
        return { label: 'in attesa di sincronizzazione', stale: true };
    }

    const ageMs = now.getTime() - synced.getTime();
    return { label: 'agg. ' + relativeAge(ageMs), stale: ageMs >= BANK_BALANCE_STALE_AFTER_MS };
}

/**
 * A broker sync (e.g. Scalable) goes stale sooner than a bank balance: it runs
 * once a day through a local proxy whose session lasts only ~8h, so a missed
 * day or two likely means the proxy is down or the session expired — surface it
 * earlier than the bank window.
 */
export const BROKER_SYNC_STALE_AFTER_MS = 2 * 24 * 60 * 60 * 1000;

/**
 * Describe how fresh a broker sync is. Same shape as bankFreshness but with the
 * shorter broker staleness window.
 */
export function brokerFreshness(
    syncedAt: string | null | undefined,
    now: Date = new Date(),
): PriceFreshness {
    if (!syncedAt) {
        return { label: 'in attesa di sincronizzazione', stale: true };
    }

    const synced = new Date(syncedAt);
    if (Number.isNaN(synced.getTime())) {
        return { label: 'in attesa di sincronizzazione', stale: true };
    }

    const ageMs = now.getTime() - synced.getTime();
    return { label: 'agg. ' + relativeAge(ageMs), stale: ageMs >= BROKER_SYNC_STALE_AFTER_MS };
}

/** Render a non-negative age in milliseconds as a short Italian "... fa" label. */
function relativeAge(ageMs: number): string {
    const minutes = Math.floor(ageMs / 60_000);
    if (minutes < 1) return 'ora';
    if (minutes < 60) return `${minutes} min fa`;

    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours} ${hours === 1 ? 'ora' : 'ore'} fa`;

    const days = Math.floor(hours / 24);
    return `${days} ${days === 1 ? 'giorno' : 'giorni'} fa`;
}
