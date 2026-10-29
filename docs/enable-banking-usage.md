# Enable Banking — how to connect / renew a bank account

Open banking consent needs an **HTTPS** callback, but the app runs on
`http://localhost:8080`. A throwaway cloudflared tunnel bridges the two. You
only need this when **connecting a new account** or **renewing consent** (~every
90 days), so the tunnel URL changing each run is fine.

> This page is about the **development Mac**. The self-hosted deployment serves
> real HTTPS through a Tailscale sidecar, so there the redirect is registered
> once and no tunnel runs — see
> [self-hosting.md §8](self-hosting.md#8-optional-enable-banking).

Prereqs (one-time): `brew install cloudflared`. Credentials already set in
`.env` (`ENABLE_BANKING_APPLICATION_ID`, `ENABLE_BANKING_PRIVATE_KEY_PATH`) and
the account is linked in the Enable Banking portal (restricted mode).

## The private key

Enable Banking authenticates the app with a **JWT signed by an RSA private
key** — the `.pem` downloaded once when the application is registered. It is not
a password: there is no second factor behind it and nothing to rate-limit. Hold
it and you can read every account the consent covers, from anywhere, for as long
as the consent lasts. Enable Banking never shows it again, so losing it means
registering a new application and re-consenting at every bank.

### Where it goes

In the **mounted data directory**, next to the database:

| | |
| --- | --- |
| On the host | `~/wealth-tracker-data/enable-banking.pem` |
| Inside the container | `/app/storage/app/enable-banking.pem` |
| `.env` | `ENABLE_BANKING_PRIVATE_KEY_PATH=/app/storage/app/enable-banking.pem` |

`.env` holds the *path*; the path points inside the container. That is the one
thing that trips people moving to a new machine — copying `.env` carries the
setting over and leaves the key behind, and the failure then reads as a
credentials problem (see [self-hosting.md §8](self-hosting.md#8-optional-enable-banking)).

### Permissions

```bash
chmod 600 ~/wealth-tracker-data/enable-banking.pem
chown <the uid the app runs as> ~/wealth-tracker-data/enable-banking.pem
```

`0600`, owner only. The data directory is a normal directory on the host: at
the default `0644` every other account on the machine, and every container that
mounts it, can read the key. The owner must be the uid the app runs as — in
production the entrypoint adopts the uid that owns the data directory, so a key
copied in as the same user is already right.

The app only reads this file, so it never needs write permission on it.

### Why it must never go into the image

A key baked into an image is a key published to every registry that image
reaches, present in every layer cache that ever pulled it, and readable by
anyone who can `docker save` it. Image layers are **not revocable**: deleting
the file in a later layer leaves it intact in the earlier one, and the only real
remedy is revoking the key at Enable Banking and re-consenting at every bank.

`.dockerignore` excludes `**/*.pem` and `**/*.key` so this cannot happen by
accident, and `.gitignore` excludes `*.pem` for the same reason. Both are
belt-and-braces: the key is supposed to live in the mounted data directory,
which is never part of the build context in the first place.

### If it leaks

Revoke the application in the Enable Banking portal, register a new one,
download the new key, update `ENABLE_BANKING_APPLICATION_ID` and the key file,
and re-consent at each bank. Existing consents are tied to the old application
and stop working — that is the point of revoking.

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
- The PEM key lives at `~/wealth-tracker-data/enable-banking.pem`, outside git —
  see [The private key](#the-private-key).
