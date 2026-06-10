#!/bin/bash
#
# Long-running Scalable proxy wrapper, managed by launchd
# (com.vincenzo.scalable-proxy). Keeps the unofficial proxy always listening on
# :3141 so the wealth-tracker app finds it on host.docker.internal:3141.
#
# Login + 2FA still happen in a browser ON THIS MAC, triggered from the app's
# "Collega / Riconnetti" button (POST /scalable/login -> proxy /auth/login).
#
# Installed to ~/scalable-unofficial/run.sh by scripts/scalable-proxy-setup.sh.
# This is the canonical template; edit here and re-run the installer to update.
#
set -euo pipefail

# Pinned Chrome for Testing build Puppeteer expects. Keep in sync with the
# installer (scripts/scalable-proxy-setup.sh).
CHROME_VERSION="148.0.7778.97"

cd "$HOME/scalable-unofficial"

# Self-heal Chrome: the Puppeteer cache has been observed to empty out, which
# makes /auth/login fail with a 500 ("Could not find Chrome"). Reinstall it
# before launching if the binary is missing, so a wiped cache repairs itself on
# the next launchd restart. Use the @puppeteer/browsers CLI via the node binary
# directly — `pnpm exec` breaks under Node 23 (ERR_UNKNOWN_BUILTIN_MODULE
# node:sqlite).
chrome_bin="$HOME/.cache/puppeteer/chrome/mac_arm-${CHROME_VERSION}/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing"
if [[ ! -x "$chrome_bin" ]]; then
  browsers_cli="$(find node_modules/.pnpm -path '*@puppeteer/browsers*/lib/cjs/main-cli.js' 2>/dev/null | head -1)"
  if [[ -n "$browsers_cli" ]]; then
    node "$browsers_cli" install "chrome@${CHROME_VERSION}" --path "$HOME/.cache/puppeteer" || true
  fi
fi

# Stable gateway token: reuse the saved one (must match SCALABLE_GATEWAY_TOKEN
# in the app's .env), or mint and persist it once on first run.
if [[ -f .token ]]; then
  TOKEN="$(cat .token)"
else
  TOKEN="wt-local-$(openssl rand -hex 6)"
  echo "$TOKEN" > .token
fi

# Use the local tsx binary directly (not `pnpm exec tsx`, same Node 23 reason).
exec ./node_modules/.bin/tsx src/index.ts \
  --port 3141 \
  --token "$TOKEN" \
  --browser-profile .browser-profile
