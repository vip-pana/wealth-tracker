# Enable Banking — Phase 3 design (consent flow UI)

Branch-only working doc. Phases 1–2 (client + balance fetch into an asset) are
done. Phase 3 makes the consent flow usable from the app so account uids are
discovered and renewed automatically instead of being pasted by hand.

Verified live (2026-05-31): real balances from multiple banks read end
to end through `/auth → /sessions → /accounts/{uid}/balances`. Confirmed: uids
belong to a session that expires, so they must be stored with their session and
re-consented on expiry.

## Decisions taken
- **HTTPS callback:** a tunnel (cloudflared/ngrok) exposes `https://…/banking/callback`
  to the container's `localhost:8080`. The tunnel URL is set as the redirect on
  the Enable Banking app and in `ENABLE_BANKING_REDIRECT_URL` (.env). Tunnel URL
  changes per restart on the free tier — documented, accepted.
- **Data model:** a dedicated `bank_connections` table (session + accounts),
  rather than only fields on `assets`.
- **Scope:** full end-to-end (choose bank → consent → callback → link discovered
  accounts to assets → renew on expiry).

## Data model

`bank_connections`
- `id`
- `aspsp_name`, `aspsp_country` — the bank
- `session_id` (nullable until callback) — Enable Banking session
- `valid_until` (nullable) — session expiry from the `/auth` access window
- `status` — `pending` (auth started) | `active` (session created) | `expired`
- `state` — random string to correlate the `/auth` redirect (CSRF-style)
- timestamps

`bank_accounts` (the accounts discovered in a session)
- `id`
- `bank_connection_id` → bank_connections
- `uid` — Enable Banking account uid (used for balance calls)
- `iban`, `name`, `currency` — display
- `asset_id` (nullable) → the Asset this account's balance feeds
- timestamps

`assets` keeps `bank_synced_at`; the existing `bank_account_uid` column is
replaced by the `bank_accounts.asset_id` link (cleaner: one account → one asset,
and the uid lives with its session). Migration drops `bank_account_uid`.

## Routes (web.php, `banking.` group)
- `GET  /banking/connect`            — list aspsps for a country (Inertia or JSON for the modal)
- `POST /banking/connect`            — start `/auth` for a chosen bank; create a `pending` connection; redirect to consent URL
- `GET  /banking/callback`          — receive `code`+`state`; exchange via `/sessions`; persist session + accounts; back to Settings
- `POST /banking/accounts/{account}/link`   — link a discovered account to an asset (or create one)
- `DELETE /banking/connections/{connection}` — disconnect (forget session + accounts)

## Flow
1. Settings → "Collega conto bancario" → pick country + bank.
2. `POST /banking/connect`: `EnableBankingClient::startAuthorization(...)`, store
   `pending` connection with `state`, redirect the browser to the consent URL.
3. Bank → `GET /banking/callback?code&state`: match `state`, call
   `authorizeSession(code)`, save `session_id` + `valid_until`, upsert
   `bank_accounts` rows, mark `active`. Redirect to Settings with a flash.
4. Settings shows discovered accounts; user links each to an asset.
5. `FetchBankBalances` iterates `bank_accounts` with an `asset_id` whose
   connection is `active` and not expired; reads the balance; updates the asset.
6. On expiry (`valid_until` passed, or a 401/403 from EB), mark `expired` and
   surface a "Riconnetti" action in Settings that restarts the flow for that bank.

## Config
- `ENABLE_BANKING_REDIRECT_URL` (.env, services.php) — the tunnel callback URL.
  Must match the redirect registered on the Enable Banking app.

## Out of scope for this phase
- Transactions (only balances).
- Automatic tunnel management — the user runs the tunnel; we just read the URL
  from config.

## Honest caveats
- Free-tier tunnel URL changes on restart → the redirect must be updated in two
  places (EB portal + .env) each time. A fixed tunnel/domain removes this; left
  to the user.
- Sessions expire (~90 days or sooner per bank); renewal is manual re-consent
  via the "Riconnetti" action — no silent background refresh (PSD2 requires the
  user present for re-consent).
