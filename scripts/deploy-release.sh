#!/bin/bash
#
# Deploy a released version on the self-hosted machine: pull the image CI built
# for that tag, pin it in .env, recreate the container, and prove the app came
# back before declaring success.
#
#   ./scripts/deploy-release.sh 1.6.0        # or v1.6.0
#
# This is also the only command GitHub Actions can run over ssh (see
# docs/self-hosting.md "Automatic deploys"): the authorized_keys entry forces
# it, so a stolen workflow secret cannot do anything but deploy a valid tag.
# That is why the version is validated as a version and nothing here interpolates
# it into a shell command.
#
# It refuses to deploy — rather than trying and failing halfway — when the new
# release needs an .env key this machine does not have. Unattended deploys were
# avoided for exactly that failure mode, and this check is what makes them safe:
# a missing key would otherwise take the app down at 3am with no one watching.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$ROOT/.env"
COMPOSE=(docker compose -f "$ROOT/docker-compose.prod.yml")

# Work from the repo, whatever directory the caller was in. Every path below is
# already absolute, but `docker compose` stats the working directory while
# validating the compose file — and when the deploy arrives over ssh the cwd is
# the deploy account's home, which this user cannot enter:
#   validating docker-compose.prod.yml: stat .: permission denied
cd "$ROOT"
IMAGE="ghcr.io/vip-pana/wealth-tracker"

# How long to wait for /up after recreating. The container's own start_period is
# 90s, and a cold Laravel boot with config caching lands well inside that.
HEALTH_TIMEOUT=120

log() { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
fail() { printf '\033[31mFAILED: %s\033[0m\n' "$1" >&2; exit 1; }

# --- input ------------------------------------------------------------------
# Accepted shape is a release version and nothing else. Anything that is not
# 1.2.3 or v1.2.3 stops here, before it can reach git, docker, or a shell.
RAW="${1:-}"
[ -n "$RAW" ] || fail "usage: deploy-release.sh <version>   (e.g. 1.6.0)"

if ! [[ "$RAW" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    fail "'$RAW' is not a release version (expected 1.2.3 or v1.2.3)"
fi

VERSION="${RAW#v}"
TAG="v$VERSION"

[ -f "$ENV_FILE" ] || fail ".env not found at $ENV_FILE"

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

PREVIOUS="$(env_get APP_VERSION)"

log "Deploying $VERSION (currently ${PREVIOUS:-unpinned})"

if [ "$PREVIOUS" = "$VERSION" ]; then
    # Not an error: a re-run of the same release is how you recover from a
    # half-finished deploy, and pulling the same digest is a no-op.
    echo "Already pinned to $VERSION — redeploying it."
fi

# --- compose files ----------------------------------------------------------
# The image carries the application code, but the compose file and this script
# come from the checkout, so the checkout has to be at the tag being deployed.
# Detached HEAD is correct here: nothing on this machine commits.
log "Checking out $TAG"
git -C "$ROOT" fetch --tags --quiet origin || fail "cannot reach the git remote"
git -C "$ROOT" rev-parse --verify --quiet "refs/tags/$TAG" >/dev/null \
    || fail "tag $TAG does not exist on the remote"

# Uncommitted changes here are either an experiment someone left behind or an
# edit to a tracked file that the checkout would silently discard. .env is
# untracked, so this never trips on the file the deploy actually rewrites.
if ! git -C "$ROOT" diff --quiet || ! git -C "$ROOT" diff --cached --quiet; then
    fail "the checkout has uncommitted changes — resolve them before deploying"
fi

git -C "$ROOT" checkout --quiet "$TAG"

# --- env guard --------------------------------------------------------------
# The failure mode this exists for: a release adds a required .env key, the
# deploy runs unattended, and the app comes up broken at 3am. So the question is
# specifically "does this upgrade introduce keys I do not have" — asked against
# the version being replaced, not against the whole .env.example.
#
# Comparing against the whole file instead would list every optional key the
# machine never set (most have a default in config/, which is why the app has
# been running fine without them), and a guard that always complains gets
# switched off. Optional keys are still surfaced, as a warning, below.
if [ -n "$PREVIOUS" ] && git -C "$ROOT" rev-parse --verify --quiet "refs/tags/v$PREVIOUS" >/dev/null; then
    log "Checking for new .env keys since $PREVIOUS"
    NEW_KEYS="$(
        comm -23 \
            <(git -C "$ROOT" show "$TAG:.env.example" | grep -oE '^[A-Z_][A-Z0-9_]*=' | tr -d '=' | sort -u) \
            <(git -C "$ROOT" show "v$PREVIOUS:.env.example" | grep -oE '^[A-Z_][A-Z0-9_]*=' | tr -d '=' | sort -u)
    )"

    # Of the keys this release adds, the ones this machine has never seen.
    MISSING="$(comm -23 \
        <(printf '%s\n' "$NEW_KEYS" | sed '/^$/d' | sort -u) \
        <(grep -oE '^[A-Z_][A-Z0-9_]*=' "$ENV_FILE" | tr -d '=' | sort -u))"

    if [ -n "$MISSING" ]; then
        echo "$MISSING" | sed 's/^/  - /' >&2
        fail "$TAG introduces .env keys this machine does not have (listed above).
Add them to $ENV_FILE, then re-run. Nothing was changed: the app is still on $PREVIOUS.
If they are optional, add them empty — this check only asks whether you saw them."
    fi
else
    # First deploy through this script, or a previous version whose tag is gone.
    # There is no baseline to diff against, so this cannot be checked.
    echo "No previous release to compare .env against; skipping the new-key check."
fi

# --- backup -----------------------------------------------------------------
# Before anything is replaced. A deploy should not be the reason a database is
# unrecoverable, and the app is still healthy at this point.
log "Backing up the database"
"${COMPOSE[@]}" exec -T app php artisan backup:run \
    || fail "backup failed — refusing to deploy without one"

# --- pull -------------------------------------------------------------------
log "Pulling $IMAGE:$VERSION"
docker pull --quiet "$IMAGE:$VERSION" \
    || fail "no image published for $VERSION (did the Deploy workflow succeed?)"

# --- switch -----------------------------------------------------------------
# APP_VERSION is what compose reads, so this line is the deploy. Rolling back is
# the same line with the old value: the previous image is still in the local
# store, so it costs no build and no network.
log "Recreating the app on $VERSION"
env_set APP_VERSION "$VERSION"

rollback() {
    printf '\033[31m%s\033[0m\n' "Health check failed — rolling back to ${PREVIOUS:-latest}" >&2
    if [ -n "$PREVIOUS" ]; then
        env_set APP_VERSION "$PREVIOUS"
    else
        # Nothing was pinned before, so there is no known-good version to name.
        # Leaving the new pin in place is still better than pointing at `latest`,
        # which would resolve to this same broken release anyway.
        echo "No previous APP_VERSION to restore; leaving $VERSION pinned." >&2
    fi
    # Back to the previous release's compose files by name, not `checkout -`:
    # that means "wherever HEAD was last", which after a second failed attempt
    # is the broken tag rather than the good one.
    if [ -n "$PREVIOUS" ]; then
        git -C "$ROOT" checkout --quiet "v$PREVIOUS" 2>/dev/null || true
    fi
    "${COMPOSE[@]}" up -d app >/dev/null 2>&1 || true
    fail "$VERSION did not come up healthy. Rolled back; check the logs:
  docker compose -f docker-compose.prod.yml logs --tail=80 app"
}

# Only `app` is recreated. The tailscale sidecar holds the node identity, and
# recreating it risks the tailnet name drifting to wealth-tracker-1 — which
# silently breaks APP_URL and the registered bank redirect.
"${COMPOSE[@]}" up -d app || rollback

# --- verify -----------------------------------------------------------------
# Compose returning 0 only means the container started, which a container whose
# app throws on boot also does. Ask the app.
log "Waiting for /up (max ${HEALTH_TIMEOUT}s)"
deadline=$((SECONDS + HEALTH_TIMEOUT))
until "${COMPOSE[@]}" exec -T app \
        php -r 'exit(@file_get_contents("http://127.0.0.1:8000/up") === false ? 1 : 0);' 2>/dev/null; do
    [ "$SECONDS" -lt "$deadline" ] || rollback
    sleep 3
done

# The queue worker and the scheduler are separate processes in the same
# container, and the app answers /up perfectly well without them: a dead worker
# means the advisor never replies and no backup ever runs, with nothing failing
# loudly. So they are checked explicitly.
log "Checking the worker and scheduler"
for process in queue:work schedule:work; do
    "${COMPOSE[@]}" exec -T app sh -c \
        "for p in /proc/[0-9]*; do tr '\\0' ' ' < \$p/cmdline 2>/dev/null | grep -q '$process' && exit 0; done; exit 1" \
        || fail "$process is not running — the container is up but incomplete:
  docker compose -f docker-compose.prod.yml logs --tail=80 app"
done

log "Deployed $VERSION"
"${COMPOSE[@]}" ps app
