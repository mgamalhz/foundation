# Working on this repository

Monorepo of independent Laravel packages under `packages/*`. Each package has its
own `composer.json` and is published separately; the root `composer.json` only
exists to develop and test them together.

## Commands

- `vendor/bin/pest` — run every package's tests (must stay green)
- `vendor/bin/pest --testsuite=lifecycle` — one package
- `vendor/bin/pint` — format before committing

## Rules

- A package may depend on `mgamalhz/support`, never on another feature package.
  Cross-package behaviour goes through events and `class_exists()` checks
  (see `AuditServiceProvider` listening for lifecycle events).
- Every public behaviour has a test. Tests use the fixtures in `tests/Fixtures`.
- Exception messages are part of the API: say what happened, what is allowed,
  and what to do instead. Agents read them and fix their code from them.
- When behaviour changes, update the package's skill in
  `resources/boost/skills/*/SKILL.md` in the same commit — the skill is what
  coding agents learn the package from.
- No code copied from client projects. Designs and lessons only.
