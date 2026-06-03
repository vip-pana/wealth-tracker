#!/bin/bash
#
# Start an HTTPS tunnel for the Enable Banking consent callback and wire it up.
#
# Open banking consent needs an https redirect, but the app runs on
# http://localhost:8080. This starts a throwaway cloudflared tunnel, captures
# its https URL, writes it into .env (ENABLE_BANKING_REDIRECT_URL), clears the
# app config, and prints the one manual step left: pasting that URL into the
# Enable Banking portal (their API can't set redirects).
#
# Run from the repo root:  ./scripts/tunnel.sh
# Leave it running for the whole connect/renew flow; Ctrl-C stops the tunnel.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"
APP_PORT="${APP_PORT:-8080}"

command -v cloudflared >/dev/null || { echo "cloudflared not found. Install with: brew install cloudflared" >&2; exit 1; }
[ -f "$ENV_FILE" ] || { echo ".env not found at $ENV_FILE" >&2; exit 1; }

LOG="$(mktemp -t wt-tunnel)"
cleanup() { [ -n "${CF_PID:-}" ] && kill "$CF_PID" 2>/dev/null || true; rm -f "$LOG"; }
trap cleanup EXIT INT TERM

echo "Starting cloudflared tunnel → http://localhost:$APP_PORT …"
cloudflared tunnel --url "http://localhost:$APP_PORT" >"$LOG" 2>&1 &
CF_PID=$!

# Wait for cloudflared to print its public URL.
URL=""
for _ in $(seq 1 30); do
    URL="$(grep -oE 'https://[a-z0-9-]+\.trycloudflare\.com' "$LOG" | head -1 || true)"
    [ -n "$URL" ] && break
    sleep 1
done
[ -n "$URL" ] || { echo "Timed out waiting for the tunnel URL. Tunnel log:" >&2; tail -20 "$LOG" >&2; exit 1; }

REDIRECT="$URL/banking/callback"

# Update ENABLE_BANKING_REDIRECT_URL in .env (add the key if missing).
if grep -q '^ENABLE_BANKING_REDIRECT_URL=' "$ENV_FILE"; then
    sed -i '' "s|^ENABLE_BANKING_REDIRECT_URL=.*|ENABLE_BANKING_REDIRECT_URL=$REDIRECT|" "$ENV_FILE"
else
    printf '\nENABLE_BANKING_REDIRECT_URL=%s\n' "$REDIRECT" >>"$ENV_FILE"
fi

docker compose exec -T app php artisan config:clear >/dev/null 2>&1 || true

cat <<EOF

────────────────────────────────────────────────────────────────────
  Tunnel ready.  ✅  .env updated, app config cleared.

  ONE manual step — paste this redirect into the Enable Banking portal
  (your app → Allowed redirect URLs), then save:

      $REDIRECT

  Portal:  https://enablebanking.com/  (Control Panel → API applications)

  Then open the app THROUGH THE TUNNEL and connect/renew:

      $URL/settings

  Leave this terminal open during the flow. Ctrl-C when done.
────────────────────────────────────────────────────────────────────

EOF

# Keep the tunnel alive in the foreground.
wait "$CF_PID"
