# Ideas

Write it down, don't build it. Ideas enter the code only when a real project needs them.

- `foundation:doctor` — routes without auth/permission, models with `$guarded = []`, webhooks without signature checks. Wraps Larastan + `composer audit`. (enlightn is abandoned — possible open-source piece later.)
- Concurrency tests for `lifecycle` against PostgreSQL in CI (row locks are a no-op on SQLite).
- `ui/` — React + shadcn + TanStack kit: tokens → components → hooks. First components when a project needs them: `DataTable`, `StatusTimeline`, `ActivityLog`.
- `project-kickoff` skill in `~/.claude/skills` — read CATALOG.md at project start, check HARVEST.md at feature end.
- Open-source later: `lifecycle` and `doctor` as standalone packages.
