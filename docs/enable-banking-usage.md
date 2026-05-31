# Enable Banking — how to connect / renew a bank account

Open banking consent needs an **HTTPS** callback, but the app runs on
`http://localhost:8080`. A throwaway cloudflared tunnel bridges the two. You
only need this when **connecting a new account** or **renewing consent** (~every
90 days), so the tunnel URL changing each run is fine.

Prereqs (one-time): `brew install cloudflared`. Credentials already set in
`.env` (`ENABLE_BANKING_APPLICATION_ID`, `ENABLE_BANKING_PRIVATE_KEY_PATH`) and
the account is linked in the Enable Banking portal (restricted mode).

## Steps

1. **Start the tunnel** (leave this terminal open for the whole flow):
   ```
   cloudflared tunnel --url http://localhost:8080
   ```
   It prints an HTTPS URL, e.g. `https://random-words.trycloudflare.com`.

2. **Set the redirect in two places — they must match exactly:**
   - Enable Banking portal → your app → *Allowed redirect URLs*: add
     `https://random-words.trycloudflare.com/banking/callback`
   - `.env`: `ENABLE_BANKING_REDIRECT_URL=https://random-words.trycloudflare.com/banking/callback`
   - then `docker compose exec app php artisan config:clear`

3. **Open the app through the tunnel URL** (not localhost), go to
   Settings → *Conti bancari* → *Collega conto*, pick the bank, consent.
   The bank redirects back through the tunnel; the app stores the session and
   discovers the accounts.

4. **Link each discovered account to an asset** in the same Settings section.
   From then on, the daily price refresh (and the manual "Aggiorna prezzi"
   button) syncs that asset's value from the bank balance.

5. **Stop the tunnel** (Ctrl-C). Day-to-day use needs no tunnel — balances
   refresh server-side using the stored session until it expires.

## Renewal (~every 90 days)

When a connection shows **Scaduto** in Settings, repeat steps 1–3 for that bank
(a fresh tunnel URL each time). The account→asset links are kept.

## Notes
- The tunnel URL changes every restart; that's why the redirect is updated each
  time. A fixed URL would need a paid/owned domain — not worth it for ~4×/year.
- The PEM key lives at `~/wealth-tracker-data/enable-banking.pem`, outside git.
