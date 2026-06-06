#!/bin/bash
#
# One-command setup for the unofficial Scalable Capital proxy on macOS.
#
# Clones the upstream proxy, installs its deps + a pinned Chrome for Testing,
# drops in our always-on launchd agent, and starts it. Idempotent: safe to
# re-run to repair or update an existing install.
#
# After it finishes, paste the printed gateway token into the app's .env
# (SCALABLE_GATEWAY_TOKEN) and clear the config — see docs/scalable-proxy.md.
#
# Stopgap until the official Scalable CLI is allowlisted.
#
set -euo pipefail

# ── Config ──────────────────────────────────────────────────────────────────
UPSTREAM_REPO="https://github.com/ffischbach/unofficial-scalable-capital-api"
PROXY_DIR="$HOME/scalable-unofficial"
CHROME_VERSION="148.0.7778.97"   # keep in sync with run.sh
# The default `pnpm` is a corepack shim that crashes under Node 23
# (ERR_UNKNOWN_BUILTIN_MODULE node:sqlite). Pin a working pnpm via corepack.
PNPM="corepack pnpm@9"
PLIST_LABEL="com.vincenzo.scalable-proxy"
PLIST_DEST="$HOME/Library/LaunchAgents/${PLIST_LABEL}.plist"

# Resolve this script's directory so we can find the template files.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE_DIR="$SCRIPT_DIR/scalable-proxy"

say() { printf '\n\033[1m▶ %s\033[0m\n' "$1"; }

# ── 1. Pre-checks ─────────────────────────────────────────────────────────────
say "Checking prerequisites"
missing=()
for cmd in git node corepack openssl curl; do
  command -v "$cmd" >/dev/null 2>&1 || missing+=("$cmd")
done
if [[ ${#missing[@]} -gt 0 ]]; then
  echo "Missing required tools: ${missing[*]}" >&2
  echo "Install them first (e.g. via Homebrew) and re-run." >&2
  exit 1
fi
if [[ ! -d "$TEMPLATE_DIR" ]]; then
  echo "Template dir not found: $TEMPLATE_DIR" >&2
  exit 1
fi

# ── 2. Clone (or reuse) the proxy ─────────────────────────────────────────────
if [[ -d "$PROXY_DIR/.git" ]]; then
  say "Proxy already cloned at $PROXY_DIR — updating"
  git -C "$PROXY_DIR" pull --ff-only || echo "  (pull skipped/failed — keeping current checkout)"
elif [[ -d "$PROXY_DIR" ]]; then
  say "Proxy dir exists at $PROXY_DIR (no .git) — reusing as-is"
else
  say "Cloning proxy from upstream"
  git clone "$UPSTREAM_REPO" "$PROXY_DIR"
fi

# ── 3. Install dependencies ───────────────────────────────────────────────────
say "Installing proxy dependencies (pnpm via corepack)"
( cd "$PROXY_DIR" && $PNPM install --frozen-lockfile )

# ── 4. Install pinned Chrome for Testing ──────────────────────────────────────
# Use the @puppeteer/browsers CLI via node directly: `pnpm exec` breaks under
# Node 23 (ERR_UNKNOWN_BUILTIN_MODULE node:sqlite).
say "Installing Chrome for Testing ($CHROME_VERSION)"
chrome_bin="$HOME/.cache/puppeteer/chrome/mac_arm-${CHROME_VERSION}/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing"
if [[ -x "$chrome_bin" ]]; then
  echo "  Already present — skipping."
else
  browsers_cli="$(find "$PROXY_DIR/node_modules/.pnpm" -path '*@puppeteer/browsers*/lib/cjs/main-cli.js' 2>/dev/null | head -1)"
  if [[ -z "$browsers_cli" ]]; then
    echo "Could not locate @puppeteer/browsers CLI under node_modules." >&2
    exit 1
  fi
  node "$browsers_cli" install "chrome@${CHROME_VERSION}" --path "$HOME/.cache/puppeteer"
fi

# ── 5. Install our wrapper + gateway token ────────────────────────────────────
say "Installing run.sh wrapper"
cp "$TEMPLATE_DIR/run.sh" "$PROXY_DIR/run.sh"
chmod +x "$PROXY_DIR/run.sh"

if [[ -f "$PROXY_DIR/.token" ]]; then
  TOKEN="$(cat "$PROXY_DIR/.token")"
else
  TOKEN="wt-local-$(openssl rand -hex 6)"
  echo "$TOKEN" > "$PROXY_DIR/.token"
fi

# ── 6. Install + (re)load the launchd agent ───────────────────────────────────
say "Installing launchd agent"
mkdir -p "$HOME/Library/LaunchAgents"
sed "s|__HOME__|$HOME|g" "$TEMPLATE_DIR/${PLIST_LABEL}.plist" > "$PLIST_DEST"
launchctl unload "$PLIST_DEST" 2>/dev/null || true
launchctl load "$PLIST_DEST"

# ── 7. Health check ───────────────────────────────────────────────────────────
say "Waiting for the proxy to come up"
ok=""
for _ in $(seq 1 20); do
  if curl -fsS -m 3 -H "X-Gateway-Token: $TOKEN" http://127.0.0.1:3141/auth/status >/dev/null 2>&1; then
    ok=1
    break
  fi
  sleep 1
done

if [[ -n "$ok" ]]; then
  echo "  Proxy is up on http://127.0.0.1:3141"
else
  echo "  Proxy did not respond yet — check $PROXY_DIR/proxy.error.log" >&2
fi

cat <<EOF

✅ Done. Next steps in the wealth-tracker app:

  1. In .env set:
       SCALABLE_BALANCE_URL=http://host.docker.internal:3141
       SCALABLE_GATEWAY_TOKEN=$TOKEN
       SCALABLE_CASH_CATEGORY_ID=<id of your Liquidità category>
       SCALABLE_CASH_ASSET_NAME=Scalable Liquidità
  2. Clear the config:
       docker compose exec app php artisan config:clear
  3. In the app: Impostazioni → Scalable Capital → "Collega / Riconnetti"
     (a browser opens on this Mac for login + 2FA).

Manage the proxy:
  launchctl list | grep scalable
  launchctl unload "$PLIST_DEST"   # stop
  launchctl load   "$PLIST_DEST"   # start
Logs: $PROXY_DIR/proxy.log, $PROXY_DIR/proxy.error.log
EOF
