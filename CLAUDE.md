@AGENTS.md

## Additional context

Load these docs when working in the relevant area:

- **Testing** → @docs/testing.md
- **Database & Backup** → @docs/database.md

## Claude-specific instructions

- Do not add docblocks, comments, or type annotations to code you did not change.
- Do not create new files unless strictly necessary — prefer editing existing ones.
- Do not add error handling for scenarios that cannot happen given the current architecture.
- After any PHP change, run `composer analyse` to verify PHPStan level 9 still passes.
- After any PHP style change, run `composer lint` to verify Pint passes; fix with `composer lint:fix`.
- After any TypeScript/React change, run `pnpm run typecheck` and `pnpm run lint`.
