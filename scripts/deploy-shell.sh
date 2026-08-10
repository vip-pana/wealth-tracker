#!/bin/bash
#
# Login shell for the "deploy" account: the only thing GitHub Actions can run on
# this machine. There is no path to an interactive prompt — the shell IS this.
#
# One wrapper for every app on the box. The app name is an argument, resolved
# against the whitelist below: adding a third app is a line here plus a line in
# sudoers, and never a second Unix account.
#
# Lives in a repo to be reviewable and reproducible, but runs from outside it:
# it must not be editable by the account it protects, and the checkouts are
# owned by pana. Install it (as root) with:
#
#   install -m 755 -o root -g root scripts/deploy-shell.sh /usr/local/sbin/deploy-shell
#   usermod --shell /usr/local/sbin/deploy-shell deploy   # or useradd, first time
#   cat > /etc/sudoers.d/deploy <<'EOF'
#   deploy ALL=(pana) NOPASSWD: /home/pana/wealth-tracker/scripts/deploy-release.sh
#   deploy ALL=(pana) NOPASSWD: /home/pana/funeral-docs/scripts/deploy-release.sh
#   EOF
#   chmod 440 /etc/sudoers.d/deploy && visudo -c
#
# Re-run the install line after changing this file — the copy under
# /usr/local/sbin is what actually runs.
#
# Tailscale SSH authenticates the caller as tag:ci and the ACL pins the Unix user
# to "deploy", so reaching this point already means "a GitHub Actions runner".
# What is still untrusted is the *argument*: a compromised workflow could ask for
# anything. Hence: one command, two arguments, both validated.
#
# The sudoers rules name the scripts but not their arguments, so this wrapper is
# not the only thing standing between a bad argument and the deploy: each
# deploy-release.sh re-validates the version itself, and neither ever
# interpolates it into a shell command.
#
set -euo pipefail

# The only deployable apps. Adding one is a branch in resolve_app below plus a
# line in /etc/sudoers.d/deploy.
KNOWN_APPS="wealth-tracker funeral-docs"

# Maps an app name to its deploy script. A `case` rather than an associative
# array so this runs under any bash — including the 3.2 on a Mac, where an
# untested wrapper is exactly how the argument checks below stop being checked.
# The name is never interpolated into a path: an unlisted one returns nothing and
# the caller refuses, so the request can only pick from this list, never spell
# out a script of its own.
resolve_app() {
    case "$1" in
        wealth-tracker) echo /home/pana/wealth-tracker/scripts/deploy-release.sh ;;
        funeral-docs)   echo /home/pana/funeral-docs/scripts/deploy-release.sh ;;
        *)              return 1 ;;
    esac
}

# sshd hands the requested command in SSH_ORIGINAL_COMMAND for `ssh host "cmd"`,
# and in "$@" after -c. Accept either, so the workflow's shape does not matter.
REQUEST="${SSH_ORIGINAL_COMMAND:-}"
if [ -z "$REQUEST" ] && [ "${1:-}" = "-c" ]; then
    REQUEST="${2:-}"
fi

usage() {
    echo "This account only runs: deploy <app> <version>" >&2
    echo "Known apps: $KNOWN_APPS" >&2
}

# An interactive login lands here with nothing to run. Refuse rather than drop to
# a prompt: this account exists to run one command.
if [ -z "$REQUEST" ]; then
    usage
    exit 1
fi

# Split on whitespace deliberately: the request must be exactly three words. Any
# shell metacharacter is not interpreted anywhere — REQUEST is never eval'd, the
# app is looked up in an array, and the version is matched against a regex before
# it is used.
read -r -a PARTS <<<"$REQUEST"

if [ "${#PARTS[@]}" -ne 3 ] || [ "${PARTS[0]}" != "deploy" ]; then
    echo "Refused. The only accepted command is: deploy <app> <version>" >&2
    exit 1
fi

APP="${PARTS[1]}"
VERSION="${PARTS[2]}"

if ! DEPLOY_SCRIPT="$(resolve_app "$APP")"; then
    echo "Refused: '$APP' is not a deployable app." >&2
    echo "Known apps: $KNOWN_APPS" >&2
    exit 1
fi

if ! [[ "$VERSION" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Refused: '$VERSION' is not a release version (expected 1.2.3)" >&2
    exit 1
fi

# Leave this account's home before handing over: it is mode 750 and owned by
# "deploy", so the target user cannot even stat it, and anything inheriting it as
# a working directory fails obscurely (docker compose reports
# "stat .: permission denied" while validating the compose file). / is readable
# by everyone; the deploy script cds into its own checkout.
cd /

# Run as pana: the checkouts, the .env files and the docker group all belong to
# that user. The sudoers rules permit these scripts and nothing else, so "deploy"
# cannot become pana for any other purpose.
exec sudo -n -u pana "$DEPLOY_SCRIPT" "$VERSION"
