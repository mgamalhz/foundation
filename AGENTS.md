# Working on this repository

One Composer package (`mgamalhz/foundation`) with its parts in `packages/*`.
The parts stay in separate folders so they can be split later.

## Commands

- `vendor/bin/pest` — all tests (must stay green)
- `vendor/bin/pest --testsuite=lifecycle` — one part
- `vendor/bin/pint` — format before committing

Requires PHP 8.4 and Laravel 13.

## Rules

- **Build on proven libraries.** Write code from scratch only where there is a
  real gap (e.g. `lifecycle`: no maintained package locks rows or keeps history).
- **Extend, don't wrap.** Projects keep using the library's own API
  (`activity()`, `$user->can()`); we add redaction, safe defaults, assertions
  and skills around it.
- A part may depend on `support`, never on another part. Cross-part behaviour
  goes through events and `class_exists()` checks.
- Every public behaviour has a test, using the fixtures in `tests/Fixtures`.
- Exception messages say what happened, what is allowed, and what to do instead.
- When behaviour changes, update the part's skill in
  `resources/boost/skills/*/SKILL.md` and `CATALOG.md` in the same commit.
- Never copy code from client projects. Designs and lessons only.
- English only — code, comments, docs, skills and commit messages. No Arabic anywhere in this repository.
- Commits are a single line, `type(module): message`, with no body or footer.
