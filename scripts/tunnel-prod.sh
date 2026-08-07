#!/bin/bash
#
# Start an HTTPS tunnel for the Enable Banking consent callback on the
# self-hosted machine. The prod counterpart of scripts/tunnel.sh, which only
# runs on the development Mac: that one uses BSD `sed -i ''` and drives the dev
# compose file, so on Linux it fails and would recreate the wrong container.
#
# Consent needs an https redirect on a public host, but the self-hosted app is
# served over plain http on the tailnet. This starts a throwaway cloudflared
# tunnel, points ENABLE_BANKING_REDIRECT_URL and APP_URL at it, recreates the
# prod container, and — unlike the dev script — restores the tailnet APP_URL on
# exit, so the app is never left pointing at a dead tunnel.
#
# Run from the repo root on the prod machine:  ./scripts/tunnel-prod.sh
# Leave it running for the whole connect/renew flow; Ctrl-C stops the tunnel.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"
COMPOSE=(docker compose -f "$ROOT/docker-compose.prod.yml")
# The prod container publishes 8080 on BIND_ADDRESS, not on loopback, so the
# tunnel has to target that address — cloudflared reaching for localhost gets
# connection refused.
APP_PORT="${APP_PORT:-8080}"

command -v cloudflared >/dev/null || {
    echo "cloudflared not found. Install it (no root needed):" >&2
    echo "  curl -fsSL -o ~/.local/bin/cloudflared https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-\$(uname -m | sed 's/x86_64/amd64/;s/aarch64/arm64/')" >&2
    echo "  chmod +x ~/.local/bin/cloudflared" >&2
    exit 1
}
[ -f "$ENV_FILE" ] || { echo ".env not found at $ENV_FILE" >&2; exit 1; }

env_get() { grep -m1 "^$1=" "$ENV_FILE" | cut -d= -f2- || true; }

env_set() {
    local key="$1" value="$2"
    if grep -q "^$key=" "$ENV_FILE"; then
        # GNU sed: no empty-string argument after -i (that is the BSD form).
        sed -i "s|^$key=.*|$key=$value|" "$ENV_FILE"
    else
        printf '\n%s=%s\n' "$key" "$value" >>"$ENV_FILE"
    fi
}

BIND_ADDRESS="$(env_get BIND_ADDRESS)"
TUNNEL_TARGET="http://${BIND_ADDRESS:-127.0.0.1}:$APP_PORT"

# Remembered so the app goes back to its tailnet origin when the flow ends.
ORIGINAL_APP_URL="$(env_get APP_URL)"
[ -n "$ORIGINAL_APP_URL" ] || { echo "APP_URL missing from .env — refusing to overwrite it blindly." >&2; exit 1; }

LOG="$(mktemp -t wt-tunnel.XXXXXX)"
RESTORED=0

restore() {
    [ "$RESTORED" = 1 ] && return
    RESTORED=1
    [ -n "${CF_PID:-}" ] && kill "$CF_PID" 2>/dev/null || true
    rm -f "$LOG"
    echo
    echo "Restoring APP_URL to $ORIGINAL_APP_URL and recreating the container …"
    # ENABLE_BANKING_REDIRECT_URL keeps the (now dead) tunnel value on purpose:
    # the URL registered in the Enable Banking portal has to match whatever the
    # next run sets, and a stale value is visibly wrong in the UI rather than
    # silently plausible.
    env_set APP_URL "$ORIGINAL_APP_URL"
    "${COMPOSE[@]}" up -d --force-recreate app >/dev/null 2>&1 || true
    echo "Done. The app is back on $ORIGINAL_APP_URL"
}
trap restore EXIT INT TERM

echo "Starting cloudflared tunnel → $TUNNEL_TARGET …"
cloudflared tunnel --url "$TUNNEL_TARGET" >"$LOG" 2>&1 &
CF_PID=$!

URL=""
for _ in $(seq 1 30); do
    URL="$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$LOG" | head -1 || true)"
    [ -n "$URL" ] && break
    sleep 1
done
[ -n "$URL" ] || { echo "Timed out waiting for the tunnel URL. Tunnel log:" >&2; tail -20 "$LOG" >&2; exit 1; }

REDIRECT="$URL/banking/callback"

# APP_URL has to move to the https origin too: served over the tunnel while
# APP_URL still says http://, Inertia emits http asset URLs and the browser
# blocks them as mixed content in the middle of the consent flow.
env_set ENABLE_BANKING_REDIRECT_URL "$REDIRECT"
env_set APP_URL "$URL"

# The prod entrypoint runs config:cache at startup, so a restart would keep
# serving the cached old values — only a recreate picks these up.
echo "Recreating the app container so it picks up the tunnel URL …"
"${COMPOSE[@]}" up -d --force-recreate app >/dev/null 2>&1 || true

# Wait for the app to answer through the tunnel before handing it over, so a
# still-booting container doesn't look like a broken tunnel.
for _ in $(seq 1 30); do
    [ "$(curl -s -o /dev/null -w '%{http_code}' "$URL/up" || true)" = "200" ] && break
    sleep 2
done

cat <<EOF

────────────────────────────────────────────────────────────────────
  Tunnel ready.  ✅  .env updated, prod container recreated on https.

  ONE manual step — paste this redirect into the Enable Banking portal
  (your app → Allowed redirect URLs), then save:

      $REDIRECT

  Portal:  https://enablebanking.com/  (Control Panel → API applications)

  Then open the app THROUGH THE TUNNEL and connect/renew:

      $URL/settings

  Leave this terminal open during the flow. Ctrl-C when done —
  APP_URL goes back to $ORIGINAL_APP_URL automatically.
────────────────────────────────────────────────────────────────────

EOF

wait "$CF_PID"
