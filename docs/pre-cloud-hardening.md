# Pre-Cloud Hardening Checklist

Wealth Tracker runs **local-only** today (Docker on the owner's machine, no
network exposure). Everything here is what must be done **before exposing it to
the internet** (tunnel, VPS, cloud). Source: the 2026-06-15 security audit
(Section B). Nothing below is exploitable while the app stays on localhost.

The single hard dependency: **B1 (authentication) must come first** — every
other item is either pointless or only partially effective without it.

## Order of operations

1. **B1 — Authentication on every route** *(not done — the big one)*
   - No route has any `auth` middleware today; every `FormRequest::authorize()`
     returns `true`; there is no `user_id` anywhere in the schema.
   - Add single-user auth (native Laravel login or a token), then wrap **all**
     routes in `Route::middleware('auth')->group(...)` in `routes/web.php`.
   - This is the prerequisite for B3, B4, and meaningful rate limiting.

2. **B2 — Production env flags** *(deploy-time `.env`, not a code change)*
   Set on the exposed deployment's `.env` (which is gitignored/local):
   - `APP_ENV=production`
   - `APP_DEBUG=false`  ← otherwise an error leaks an Ignition stack trace
     (file paths, PEM key path, config) to anyone, since routes are public.
   - `LOG_LEVEL=warning`
   With `APP_ENV=production`, the session `encrypt` and `secure` cookie defaults
   (see `config/session.php`) flip on automatically — see B7 below.

3. **B3 — Scope banking to a user** *(not done, depends on B1)*
   - Add `user_id` to `bank_connections`; filter by `auth()->id()`.
   - Put `auth` middleware on `/banking/callback` and the rest of the banking
     routes (`app/Http/Controllers/Banking/`).

4. **B4 — Scope Scalable login to a user** *(not done, depends on B1)*
   - `auth` middleware on the `/scalable/cli/*` routes.
   - The login state uses a global cache key `scalable.login` — make it
     per-user so concurrent logins don't clobber each other.

5. **B8 — Rate limiting** *(not done, only useful after B1)*
   - `->middleware('throttle:10,1')` on `POST /backup`, `/scalable/cli/login`,
     `/banking/connect`, `/import/csv`. The real threat is DoS / disk
     exhaustion (synchronous `VACUUM` on `/backup`), not data theft.

6. **B7 — Session & cookie hardening** *(DONE in code; verify at deploy)*
   - `config/session.php` now defaults `encrypt` and `secure` to `true` when
     `APP_ENV=production`, `false` otherwise. No action needed beyond setting
     `APP_ENV=production` (B2). Local http dev is unaffected.
   - Serve over HTTPS only (the tunnel already terminates TLS).

7. **B9 — File permissions & non-root container** *(not done)*
   - `chmod(0600)` on backup files after `VACUUM INTO`
     (`app/Actions/Backup/CreateBackup.php`); backup dir `0700`.
   - Run the container as a dedicated app user instead of root (Dockerfile).

8. **B6 — Enable Banking PEM key** *(not done; matters post-RCE)*
   - Key is read into memory via `file_get_contents`
     (`EnableBankingClient.php`). For cloud, inject it from a secrets manager
     and set a rotation policy. Disk perms are already 0600.
   - (B11, the log-path leak, is already fixed: the warning no longer includes
     the key path.)

9. **B12 — Trust the tunnel proxy by IP** *(LOW; defense-in-depth)*
   - `bootstrap/app.php` uses `trustProxies(at: '*')` in tunnel mode. Once the
     cloudflared egress IP is known, specify it instead of the wildcard.

10. **B13 — Log file permissions** *(LOW)*
    - `chmod 600` on `storage/logs/*.log` + rotation; centralised logging with
      redaction in cloud. No secrets are actually logged today.

## Cipher-at-rest (Section A1 — relevant locally too)

Not a Section B item but tracked here for completeness: the SQLite DB and its
Proton-synced backups are plaintext at rest. Local mitigation = **enable
FileVault** on the Mac. For cloud: SQLCipher or an encrypted filesystem, and
encrypt backups before they sync.

## Already done (Section A, merged to main)

- A2 prompt injection: user text delimited + sanitised in the AI briefing.
- A3 CSV formula injection: export prefixes `'` to formula-trigger cells.
