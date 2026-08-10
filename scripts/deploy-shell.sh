#!/bin/bash
#
# Login shell for the "deploy" account: the only thing GitHub Actions can run on
# this machine. There is no path to an interactive prompt — the shell IS this.
#
# Lives here to be reviewable and reproducible, but runs from outside the repo:
# it must not be editable by the account it protects, and the checkout is owned
# by pana. Install it (as root) with:
#
#   install -m 755 -o root -g root scripts/deploy-shell.sh /usr/local/sbin/deploy-shell
#   useradd --system --create-home --shell /usr/local/sbin/deploy-shell deploy
#   echo 'deploy ALL=(pana) NOPASSWD: /home/pana/wealth-tracker/scripts/deploy-release.sh' \
#       > /etc/sudoers.d/deploy && chmod 440 /etc/sudoers.d/deploy && visudo -c
#
# Re-run the install line after changing this file — the copy under
# /usr/local/sbin is what actually runs.
#
# Tailscale SSH authenticates the caller as tag:ci and the ACL pins the Unix user
# to "deploy", so reaching this point already means "a GitHub Actions runner".
# What is still untrusted is the *argument*: a compromised workflow could ask for
# anything. Hence: one command, one argument, validated as a version.
#
set -euo pipefail

REPO_DIR=/home/pana/wealth-tracker
DEPLOY_SCRIPT="$REPO_DIR/scripts/deploy-release.sh"

# sshd hands the requested command in SSH_ORIGINAL_COMMAND for `ssh host "cmd"`,
# and in "$@" after -c. Accept either, so the workflow's shape does not matter.
REQUEST="${SSH_ORIGINAL_COMMAND:-}"
if [ -z "$REQUEST" ] && [ "${1:-}" = "-c" ]; then
    REQUEST="${2:-}"
fi

# An interactive login lands here with nothing to run. Refuse rather than drop to
# a prompt: this account exists to run one command.
if [ -z "$REQUEST" ]; then
    echo "This account only runs: deploy <version>" >&2
    exit 1
fi

# Split on whitespace deliberately: the request must be exactly two words. Any
# shell metacharacter is not interpreted anywhere — REQUEST is never eval'd, and
# the version is matched against a regex before it is used.
read -r -a PARTS <<<"$REQUEST"

if [ "${#PARTS[@]}" -ne 2 ] || [ "${PARTS[0]}" != "deploy" ]; then
    echo "Refused. The only accepted command is: deploy <version>" >&2
    exit 1
fi

VERSION="${PARTS[1]}"

if ! [[ "$VERSION" =~ ^v?[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo "Refused: '$VERSION' is not a release version (expected 1.2.3)" >&2
    exit 1
fi

# Run as pana: the checkout, .env and the docker group all belong to that user.
# The sudoers rule permits this one command and nothing else, so "deploy" cannot
# become pana for any other purpose.
exec sudo -n -u pana "$DEPLOY_SCRIPT" "$VERSION"
