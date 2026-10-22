# Contributing to Wealth Tracker

Thanks for wanting to help. This is a small project maintained by one person, so
the process is deliberately light.

## Before you open a pull request

Wealth Tracker is released under the [AGPL-3.0](LICENSE) and is also available
under separate commercial terms. For that to stay possible, the project's owner
needs to hold rights in every contribution that are broad enough to license it
both ways — so contributors are asked to accept the
[Contributor License Agreement](CLA.md) once.

You keep the copyright in your code. The CLA grants a licence; it is not an
assignment, and it does not stop you reusing your own work elsewhere.

To accept it, read [CLA.md](CLA.md) and post this as a comment on your pull
request, from the account that authored the commits:

> I have read the Wealth Tracker Contributor License Agreement and I accept its
> terms for this and my future contributions. Full name: [name]. Email: [email].

If you are contributing on behalf of a company, read Part B and say so in the
comment — the acceptance has to come from someone authorised to bind the
company.

You only need to do this once. The acceptance is recorded in
[CONTRIBUTORS.md](CONTRIBUTORS.md).

Automated dependency updates do not need a CLA.

## Working on the code

See [AGENTS.md](AGENTS.md) for the project's conventions, and
[docs/testing.md](docs/testing.md) for how to run the tests. The short version:

```bash
composer analyse     # PHPStan level 9
composer lint        # Pint
pnpm run typecheck
pnpm run lint
php artisan test
pnpm run test
```

All of these run in CI and all of them must pass.

## Contributions produced with AI assistance

They are fine — much of this project was written that way. Review what you
submit, make sure you understand it, and mention the tool in the pull request.
