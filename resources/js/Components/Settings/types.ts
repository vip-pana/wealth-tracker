export interface PriceEntry {
    ticker: string;
    price: number | null;
    currency: string;
    expense_ratio: number | null;
    fetched_at: string | null;
    last_status: 'ok' | 'failed' | null;
    last_attempt_at: string | null;
    last_error: string | null;
}

export interface TrashedItem {
    type: string;
    label: string;
    deleted_at: string | null;
    restore_url: string;
}

export interface BankAccountEntry {
    id: number;
    iban: string | null;
    name: string | null;
    linked_asset_id: number | null;
    linked_name: string | null;
    synced_at: string | null;
    last_sync_status: 'ok' | 'failed' | null;
    last_sync_error: string | null;
}

export interface BankConnectionEntry {
    id: number;
    status: 'active' | 'pending' | 'expired';
    aspsp_name: string;
    aspsp_country: string;
    valid_until: string | null;
    accounts: BankAccountEntry[];
}

export interface BankOption {
    name: string;
    country: string;
}

export interface LinkableAsset {
    id: number;
    name: string;
}

export interface ScalableLoginState {
    status: 'idle' | 'pending' | 'url_issued' | 'complete' | 'failed';
    url: string | null;
    user_code: string | null;
    error: string | null;
    started_at: string | null;
}

export interface ScalableState {
    configured: boolean;
    cli_logged_in: boolean | null;
    last_sync_status: 'ok' | 'failed' | null;
    last_sync_error: string | null;
    last_sync_at: string | null;
    login: ScalableLoginState;
}

export interface TransactionAsset {
    id: number;
    name: string;
    quantity: number | null;
    transactions_count: number;
}

export type CategoryForm = {
    name: string;
    color: string;
    macro_category: string;
};

/** Mask an IBAN to first 4 + last 4 chars (e.g. IT09 ···· 0125); null if empty. */
export function maskIban(iban: string | null): string | null {
    if (!iban || iban.length < 8) return iban || null;
    return `${iban.slice(0, 4)} ···· ${iban.slice(-4)}`;
}
