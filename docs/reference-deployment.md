# The reference deployment

How the project's own instance runs. It is one particular arrangement — a Linux
box at home, reached over Tailscale, deployed from GitHub Actions — written
down because it is the setup that gets exercised daily and because the
reasoning transfers.

**You do not need any of this to self-host the app.** Start from
[self-hosting.md](self-hosting.md); come back here if you want HTTPS on a
private network without exposing anything, or an unattended deploy. The
application code is identical either way; only the exposure differs, so
switching later costs nothing.

What is specific to this instance, and what you would have to supply your own
version of: a tailnet, a Tailscale auth key, an S3-compatible bucket for
backups, a GitHub repository with the deploy workflow, and a Unix account on
the target machine.

## Why Tailscale rather than a published port

Tailscale is a private network between your own devices. The app is never
published to the internet, so the login is a second lock rather than the only
one. The alternative — a port on the host plus a reverse proxy plus a domain —
works, and is case (b) in [self-hosting.md §5](self-hosting.md#5-decide-how-you-reach-it);
it just means the app is reachable by anyone who finds the name.

Getting *real* HTTPS out of it is not cosmetic. It is what removes the
throwaway tunnel from the Enable Banking consent flow, and it lets the session
cookie stay `secure`.

## The sidecar

`docker-compose.prod.yml` runs a **Tailscale sidecar** that joins the tailnet as
its own node named `wealth-tracker`, obtains a Let's Encrypt certificate for
`https://wealth-tracker.<your-tailnet>.ts.net`, and proxies to the app over
loopback. The `app` container shares that network stack
(`network_mode: service:tailscale`), so the two are a single node as far as the
tailnet is concerned and the app is reachable from nowhere else. It publishes no
port on the host at all.

Install Tailscale on the host and on your phone, signing in with the same
account:

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

Two things on the admin console, both one-time:

- Turn on **HTTPS certificates** for the tailnet under
  [DNS settings](https://login.tailscale.com/admin/dns). Without it the sidecar
  cannot obtain a certificate and serve never binds 443. Do this *before* the
  first start.
- Generate a **reusable auth key** under
  [Settings → Keys](https://login.tailscale.com/admin/settings/keys). Generate a
  fresh one per machine rather than copying an existing one. The prod stack
  refuses to start without it.

Then in `.env`:

```
TS_AUTHKEY=tskey-auth-…
APP_URL=https://wealth-tracker.<your-tailnet>.ts.net
SESSION_SECURE_COOKIE=true
```

`APP_URL` has no port: the sidecar serves on 443. `.env` changes need
`--force-recreate`, not a restart:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate
```

Check it from the machine before reaching for the phone:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://wealth-tracker.<your-tailnet>.ts.net/up
```

`200` means the phone will reach it too — as long as the phone has Tailscale
connected. The `.ts.net` name resolves publicly but only answers over the
tailnet.

> **The `tailscale-state` volume holds the node identity.** Delete it and the
> next start registers a *new* node, which Tailscale names `wealth-tracker-1`.
> The hostname is baked into `APP_URL` and into the redirect registered with
> Enable Banking, so both silently break. Keep the volume across recreations.

### The bank consent redirect, with a real HTTPS name

Enable Banking sends the user back to an HTTPS callback after they approve at
their bank. With the sidecar the app already has one, so this is a one-time
registration and **no tunnel is involved**:

1. Register `https://wealth-tracker.<your-tailnet>.ts.net/banking/callback` as
   the redirect on your Enable Banking application.
2. Put the identical URL in `ENABLE_BANKING_REDIRECT_URL`.
3. `docker compose -f docker-compose.prod.yml up -d --force-recreate`.

> **Keep Tailscale connected on the device you consent from.** The bank
> redirects *your browser*, so if the phone drops off the tailnet mid-flow the
> callback lands on an unreachable host and the consent is lost — the bank side
> succeeded and the app never heard about it. Just run the flow again.

> **Earlier setups used `BIND_ADDRESS`**, publishing port 8080 on the tailnet IP
> and serving plain http. That still works if you skip the sidecar, but it costs
> you HTTPS, so the bank-consent flow goes back to needing a tunnel and
> `SESSION_SECURE_COOKIE` has to be `false`. The sidecar supersedes it; with
> `network_mode: service:tailscale` the app has no ports of its own to bind.

## Backups: why the off-site target is S3-compatible

The previous design was a `launchd` job on the development Mac invoking
`scripts/backup.sh`, which shelled out to `docker run keinos/sqlite3` and wrote
into a Proton Drive folder that the desktop client then synced. It worked, but
it only worked *on that Mac*: the schedule, the Docker dependency and the
off-site step all lived on the host. Hosting the app anywhere else silently left
it with no backups.

Backups now run inside the app, so they behave identically wherever it runs.
The tradeoff: Proton Drive has no S3 API, so the off-site target moved to an
S3-compatible provider — Cloudflare R2 here, but any of them works. The
settings are in `.env.example`, and the mechanism is in
[database.md](database.md).

## Unattended deploys

Merging release-please's release PR is the whole deploy. Publishing the release
triggers `.github/workflows/deploy.yml`, which builds the image, pushes it to
ghcr.io, and then calls `deploy-to-host.yml` to tell the machine to run it.

Unattended deploys were avoided for a long time, for a good reason: a release
that needs a new `.env` key would take the app down overnight with nobody
watching. What makes them safe is that the machine, not the workflow, decides —
`scripts/deploy-release.sh` refuses a release whose `.env` keys it does not
have, takes a backup first, and rolls back if the app does not answer. The
workflow only names a version.

The manual path is unchanged and still right for a hotfix, or when the workflow
is the thing that is broken:

```bash
./scripts/deploy-release.sh 1.7.0
```

### How the runner reaches a machine with no open ports

GitHub joins the tailnet as a throwaway node tagged `tag:ci`, which the tailnet
policy allows to reach exactly one host on exactly one port:

```json
{ "src": ["tag:ci"], "dst": ["tag:prod"], "ip": ["tcp:22"] }
```

It connects as the Unix user `deploy`, whose **login shell is
`/usr/local/sbin/deploy-shell`** — a wrapper that accepts one command,
`deploy <app> <version>`, resolves the app against a whitelist, validates the
version as a version, and executes that app's deploy script through a
single-command sudoers rule. There is no interactive shell behind that account,
and no ssh key anywhere: Tailscale SSH authenticates against the tailnet
identity.

So a leaked workflow secret buys the ability to deploy an existing release. It
does not buy a shell on the machine that holds the financial history.

That account serves every app on the box. The app is an argument checked
against a list of known names, never a path the caller supplies, so a third app
is a line in the whitelist plus a line in sudoers, not a second Unix account
with its own copy of the wrapper to drift. The wrapper is committed as
`scripts/deploy-shell.sh` and must be kept identical to the installed copy:
only `/usr/local/sbin/deploy-shell` actually runs.

The `ssh` rule for `tag:ci` uses `"action": "accept"` rather than `"check"`. A
`check` rule demands an interactive browser confirmation, which a CI runner
cannot answer — the job would hang until it timed out.

> **Tagging the host removes it from `autogroup:self`.** The default policy
> grants you SSH to *your own* devices; a tagged device has no owner, so that
> rule stops covering it and `tagOwners` grants nothing on its own. The policy
> therefore names the account explicitly — `"users": ["pana"]`, not
> `autogroup:nonroot`, which does not expand to arbitrary Unix accounts. Get
> this wrong and you lock yourself out of the machine, so land the rule in the
> same policy save as the tag.

## When something does not work here

The generic table is in
[self-hosting.md](self-hosting.md#when-something-does-not-work). These rows only
apply to this arrangement.

| Symptom | Cause |
| --- | --- |
| Stack will not start, `TS_AUTHKEY missing from .env` | The sidecar has no auth key. Generate a reusable one. |
| The tailnet name became `wealth-tracker-1` | The `tailscale-state` volume was lost, so the sidecar registered as a new node. Delete the stale node in the admin console, restore or recreate the volume, and check `APP_URL` and the registered bank redirect still match. |
| Name resolves but nothing answers, from the phone | The phone is not connected to the tailnet. `.ts.net` names resolve publicly and answer only over Tailscale. |
| Deploy job fails at the ssh step with `permission denied` | The tailnet policy no longer matches: check `tag:prod` is still on the host (a re-auth can drop it) and that the `ssh` rule for `tag:ci` names the `deploy` user. |
| Deploy job hangs at the ssh step until it times out | The `ssh` rule for `tag:ci` is `"action": "check"`, which waits for a browser confirmation nobody will give. It must be `"accept"`. |
| Deploy job fails with `Refused. The only accepted command is…` | Something asked the `deploy` account for a command other than `deploy <app> <version>`. If it arrived as two words, the installed `/usr/local/sbin/deploy-shell` is newer than the workflow that called it (or the reverse): reinstall the wrapper from `scripts/deploy-shell.sh` and check the caller passes `app:`. |
| Deploy job fails with `Refused: '<name>' is not a deployable app` | The app is not in the wrapper's whitelist. Add it there and to `/etc/sudoers.d/deploy`, then reinstall the wrapper. |
| Deploy job fails at `tailscale up` with `403 calling actor does not have enough permissions` | The OAuth client is missing the **Auth Keys → Write** scope. `Devices → Write` alone is not enough: the action mints an auth key to register the ephemeral node. |
| Deploy job fails with `validating docker-compose.prod.yml: stat .: permission denied` | Something invoked the deploy script from a directory the running user cannot enter — the `deploy` account's home is mode 750. Both scripts now `cd` away from it; an older copy of `/usr/local/sbin/deploy-shell` predates that fix. |
| The workflow is green but the app is on the old version | The host script rolled back: it found a missing `.env` key, or `/up` never answered. The job log has the reason; the app is still up on the previous release. |
| Bank consent needs a tunnel again | Something is serving plain http — the sidecar is down, or `APP_URL` was pointed back at a port. On Linux use `scripts/tunnel-prod.sh`; `scripts/tunnel.sh` is the development-Mac one and fails on BSD `sed`. |
