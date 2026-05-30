# AGENTS.md

<!-- exodia:section:overview -->
Wealth Tracker is a single-user personal wealth tracker: log assets by category each month, take point-in-time snapshots, and follow net worth through charts, forecasts and a savings goal. Laravel 12 + PHP 8.4 backend, React 19 + TypeScript over Inertia.js v2 (no REST API), SQLite, runs in Docker.

## Commands

<!-- exodia:section:commands -->
Check `composer.json` and `package.json` for scripts. Run them inside the Docker container `wealth-tracker-app-1`, not on the host. Full list and the quality gate: [context/operations/OPERATIONS.md](context/operations/OPERATIONS.md).

## Context Router

<!-- exodia:section:router -->
<!-- exodia:router:start -->
Route by task type. Read the relevant L2 module, then load L3 data (`.jsonl` / `.yaml`) only when needed. **Max 2 hops.**

| Task type | Load |
| --------- | ---- |
| System shape, routing, modules, runtime | [context/architecture/ARCHITECTURE.md](context/architecture/ARCHITECTURE.md) |
| Code conventions, idioms, testing, Claude rules | [context/design-patterns/DESIGN-PATTERNS.md](context/design-patterns/DESIGN-PATTERNS.md) |
| Domain entities, models, relationships | [context/glossary/GLOSSARY.md](context/glossary/GLOSSARY.md) |
| Commands, Docker, quality gate, backup/restore | [context/operations/OPERATIONS.md](context/operations/OPERATIONS.md) |
| Something broke, known pitfalls | [context/debugging/DEBUGGING.md](context/debugging/DEBUGGING.md) |
<!-- exodia:router:end -->

## Behavioral Rules

<!-- exodia:section:rules -->
1. **Route first.** Use the Context Router table above before loading any data file. Do not guess; the router exists to avoid guessing.
2. **Load lazily.** Never load all L3 files at once. Max 2 hops: router → L2 narrative → (optional) L3 data. If the task is answerable from L2 alone, stop there.
3. **Append only.** `.jsonl` data files are append-only. When an entry becomes obsolete, mark it `archived`; do not delete.
4. **Rationale required.** ADRs and decisions must include *why*. An entry without a reason will rot.
5. **Read before write.** Before appending to a data file, scan it for a duplicate or near-duplicate. Update or supersede rather than create a duplicate.
6. **Existing patterns win.** Read `context/design-patterns/DESIGN-PATTERNS.md` before introducing a new convention. If an existing pattern covers the case, use it.
7. **IDs are timestamps.** All L3 entries use the format `{type}_{YYYYMMDD}_{HHMMSS}_{4hex}` where `{type}` is the target file's `_schema` value (first line of the `.jsonl`). This makes IDs sortable and collision-free.
8. **Context update as final task.** When planning work with a todo list, always add a final step: "Evaluate context update." At that step, walk the §Self-Update Rules table below and decide if any entry should be captured. If nothing qualifies, skip. Do not create entries just to fill the step.
- **Operations awareness.** Check `context/operations/OPERATIONS.md` before touching user-visible text, env variables, routing, deploy config, or anything that differs by environment/tenant/variant. When in doubt, open the file.

## Self-Update Rules

<!-- exodia:section:self-update -->
The context files are **shared, living documentation** about the codebase, not personal memory. After completing a task, check whether any codebase fact, decision, or pattern (discovered or taught) should be logged for future sessions. Write in objective, third-person terms (the team decided X because Y), not first-person recollection (I learned X). **Do not ask the user for permission; just do it.** The user can always revert via git.

### When to update

Target-file paths below are repo-rooted.

| Signal during conversation | Target file | What to write |
| -------------------------- | ----------- | ------------- |
| Codebase assumption corrected by user or by evidence | L2 `.md` file for that area | Update the incorrect section |
<!-- exodia:self-update:rows:start -->
| Architecture or design decision taken by the team | `context/architecture/decisions.jsonl` | New ADR entry |
| PR review surfaces new check (prod break, near-miss) | `context/design-patterns/reviews.jsonl` | New review entry |
| API contract changes or deprecated | `context/design-patterns/reviews.jsonl` | New entry tagged `migration` with `old_pattern` / `new_pattern` |
| Domain term clarified or new entity appears | `context/glossary/glossary.yaml` | New or updated term |
| Variant-specific behavior confirmed | `context/operations/variants.yaml` | New entry under the relevant variant |
| Reproducible behaviour worth a future debugger's time: name the symptom(s), root cause, fix. Includes recurring footguns: the footgun IS the symptom. | `context/debugging/playbooks.jsonl` | New playbook entry |
| L2 guardrail grows past ~3 lines or pattern needs nuance | `context/design-patterns/docs/<slug>.md` | Spin out a deep dive, replace the L2 section body with `See [docs/<slug>.md](docs/<slug>.md) for full details.` |
<!-- exodia:self-update:rows:end -->

### How to update

1. **Read the target file first**: check for duplicates or entries that should be updated instead of duplicated.
2. **Branch-scoped dedup.** Check the current branch (`git branch --show-current`). If an entry on the same topic was added on the **current branch** (`git diff <default-branch> -- <file>`), **replace it in-place** instead of appending. Once an entry is merged, it is settled; only superseded by a new entry on a new branch.
3. **Use the existing schema**: every `.jsonl` file starts with a `_schema` line. Read `_fields` to know which keys an entry must carry. Match field names exactly. Do not invent fields. To evolve the schema, bump `_version` first.
4. **Generate the ID**: format `{type}_{YYYYMMDD}_{HHMMSS}_{4hex}`. When replacing an entry per rule 2, keep the original ID.
5. **Append, don't rewrite**: add new lines at the end of `.jsonl` files. For `.md` and `.yaml`, edit the relevant section. Exception: rule 2.
6. **Archive, don't delete.** When a `.jsonl` entry becomes obsolete, set `status: archived` (ADRs use `status: superseded` with `supersedes: <id>`).
7. **Keep entries atomic**: one insight per entry.
8. **Be concise**: write for a developer reading this months later without the conversation context.
9. **Point, don't hardcode**: never copy values that already live in source files; reference the file.

### What NOT to capture

- Anything already in the context files (check first).
- Ephemeral debugging steps that only apply to this session.
- User preferences about agent behavior (those belong in `.claude/` settings, not here).
- Information derivable from reading the code or git history (versions, ports, signatures, schemas, test names, dependency lists).

## Quick Action Table

<!-- exodia:section:quick-actions -->
| Developer says | Action sequence |
| -------------- | --------------- |
| "Add a route / controller / page" | Read `context/architecture/ARCHITECTURE.md`, then `routes/web.php` |
| "What is entity X?" | Read `context/glossary/GLOSSARY.md`, then the model in `app/Models/` |
| "How do I run / test / lint?" | Read `context/operations/OPERATIONS.md` |
| "Add a convention / new pattern" | Read `context/design-patterns/DESIGN-PATTERNS.md` first |
| "Something broke" | Read `context/debugging/DEBUGGING.md`, grep `playbooks.jsonl` |
| "Back up / restore the DB" | Read `context/operations/OPERATIONS.md` → [docs/database.md](docs/database.md) |

## Context Structure

<!-- exodia:section:structure -->
```text
AGENTS.md                     ← you are here (router)
context/
  architecture/
    ARCHITECTURE.md           system shape, routing, runtime
    decisions.jsonl           ADRs
  design-patterns/
    DESIGN-PATTERNS.md        conventions, testing, Claude rules
    reviews.jsonl             review checks, migrations
    docs/                     deep dives (spun out when a guardrail grows)
  glossary/
    GLOSSARY.md               entities and relationships
    glossary.yaml             extended terminology
  operations/
    OPERATIONS.md             commands, gate, backup/restore
    variants.yaml             per-variant behavior
  debugging/
    DEBUGGING.md              pitfalls and how to diagnose
    playbooks.jsonl           symptom → cause → fix
docs/                         hand-written guides (testing.md, database.md)
```
