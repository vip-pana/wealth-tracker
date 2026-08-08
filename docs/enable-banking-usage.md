# Enable Banking — how to connect / renew a bank account

Open banking consent needs an **HTTPS** callback, but the app runs on
`http://localhost:8080`. A throwaway cloudflared tunnel bridges the two. You
only need this when **connecting a new account** or **renewing consent** (~every
90 days), so the tunnel URL changing each run is fine.

> This page is about the **development Mac**. The self-hosted deployment serves
> real HTTPS through a Tailscale sidecar, so there the redirect is registered
> once and no tunnel runs — see
> [self-hosting.md §8](self-hosting.md#8-enable-banking).

Prereqs (one-time): `brew install cloudflared`. Credentials already set in
`.env` (`ENABLE_BANKING_APPLICATION_ID`, `ENABLE_BANKING_PRIVATE_KEY_PATH`) and
the account is linked in the Enable Banking portal (restricted mode).

## Steps

1. **Start the tunnel** (leave this terminal open for the whole flow):
   ```
   ./scripts/tunnel.sh
   ```
   It starts cloudflared, writes the https URL into `.env`
   (`ENABLE_BANKING_REDIRECT_URL`), clears the app config, and prints the one
   manual step + the URL to open.

2. **Paste the printed redirect** into the Enable Banking portal (your app →
   *Allowed redirect URLs*) and save. This is the only manual step — Enable
   Banking has no API to set redirects, and the tunnel URL changes each run.

3. **Open the app through the printed tunnel URL** (not localhost), go to
   Settings → *Conti bancari* → *Collega conto*, pick the bank, consent.
   The bank redirects back through the tunnel; the app stores the session and
   discovers the accounts.

4. **Link each discovered account to an asset** in the same Settings section.
   From then on, the daily price refresh (and the manual "Aggiorna prezzi"
   button) syncs that asset's value from the bank balance.

5. **Stop the tunnel** (Ctrl-C in the script's terminal). Day-to-day use needs
   no tunnel — balances refresh server-side using the stored session until it
   expires.

## Renewal (~every 90 days)

When a connection shows **Scaduto** in Settings, run `./scripts/tunnel.sh`
again, paste the new redirect into the portal, and use the **Riconnetti** button
on that connection. A fresh session issues new account uids, so re-link the
accounts to their assets afterwards.

## Notes
- The tunnel URL changes every restart; that's why the redirect is updated each
  time. A fixed URL would need a paid/owned domain — not worth it for ~4×/year.
- The PEM key lives at `~/wealth-tracker-data/enable-banking.pem`, outside git.
