# Scalable Capital sync (proxy setup)

Optional. Syncs Scalable Capital balances into assets. **Stopgap** until the
official Scalable CLI is allowlisted — it relies on an unofficial local proxy
that may break and may violate Scalable's ToS; use at your own risk.

## How it works

Scalable has no public API. A small Node proxy runs **on your Mac** (not in the
app container): you log in once through a real browser (with 2FA), the proxy
keeps the session (~8h), and the wealth-tracker container reads balances from it
at `http://host.docker.internal:3141`. Holdings are matched to assets by ISIN;
the daily `prices:fetch` and the "Sincronizza ora" button pull the values.

**Limit:** the 2FA login needs a visible browser on the Mac desktop. The
container can't open it — so the login step always happens on the host (one
click from the app, see below).

## Setup on a new machine

Prerequisites: `git`, `node`, `corepack`, `openssl`, `curl` (Homebrew). macOS
only (uses launchd). The installer pins pnpm via `corepack pnpm@9` — the default
`pnpm` shim crashes under Node 23.

```bash
./scripts/scalable-proxy-setup.sh
```

This clones the upstream proxy to `~/scalable-unofficial`, installs its
dependencies and a pinned Chrome for Testing, installs our always-on launchd
agent, starts the proxy, and prints a gateway token.

Then, in the app's `.env`, set (the script prints the token):

```
SCALABLE_BALANCE_URL=http://host.docker.internal:3141
SCALABLE_GATEWAY_TOKEN=<printed token>
SCALABLE_CASH_CATEGORY_ID=<id of your "Liquidità" category>
SCALABLE_CASH_ASSET_NAME=Scalable Liquidità
```

and clear the config:

```bash
docker compose exec app php artisan config:clear
```

Finally, set each Scalable holding's **ISIN** in its asset (edit modal) so the
sync can match it.

## Daily use

The proxy is always on (launchd starts it at login and restarts it if it dies).
When the ~8h session expires, the Settings → **Scalable Capital** card shows a
stale/failed state: click **"Collega / Riconnetti"** — a browser opens on the
Mac for login + 2FA, then the automatic sync resumes. No credentials ever touch
the app.

## Managing the launchd agent

```bash
launchctl list | grep scalable                                       # status
launchctl unload ~/Library/LaunchAgents/com.vincenzo.scalable-proxy.plist   # stop
launchctl load   ~/Library/LaunchAgents/com.vincenzo.scalable-proxy.plist   # start
```

Logs: `~/scalable-unofficial/proxy.log` and `proxy.error.log`.

## Troubleshooting

- **"Collega / Riconnetti" fails with a 500 / "Could not find Chrome"** — the
  Puppeteer Chrome cache was wiped. `run.sh` reinstalls it on the next restart
  (`launchctl unload`+`load`); or just re-run `./scripts/scalable-proxy-setup.sh`.
- **Card says proxy unreachable** — the proxy isn't running: `launchctl load`
  the agent, or check `proxy.error.log` (a common cause is port 3141 already
  taken by a stray instance — `lsof -ti :3141 | xargs kill`).
- **Token mismatch (401)** — `SCALABLE_GATEWAY_TOKEN` in `.env` must equal
  `~/scalable-unofficial/.token`. The token is stable; if you change it, update
  both and `config:clear`.
- **`pnpm exec` errors under Node 23** (`ERR_UNKNOWN_BUILTIN_MODULE node:sqlite`)
  — why `run.sh` and the installer call `node`/`tsx` binaries directly instead.

## Future

To be replaced by the official Scalable CLI (`sc`, bundled in the image) once
the account is allowlisted. At that point this proxy and its launchd agent can
be removed.
