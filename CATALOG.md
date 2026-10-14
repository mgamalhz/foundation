# Catalog

Read this at the start of every project. Propose only what the project needs
**now**, with a one-line reason each, and wait for approval before installing.

## lifecycle — `Mgamalhz\Lifecycle`

**Use when** a model has a status that moves through steps and the steps
matter: orders, bookings, requests, subscriptions, invoices, tickets, payouts.

**Don't use** for simple flags (`is_active`, `is_featured`) or a status nobody
changes after creation.

Gives: declared transitions, row-locked `transitionTo()`, guards with readable
reasons, history table, `StateTransitioned` after commit, strict mode (direct
assignment throws), `lifecycle:show`, test assertions.
Skill: `packages/lifecycle/resources/boost/skills/state-lifecycle`.

## audit — `Mgamalhz\Audit` (needs `spatie/laravel-activitylog`)

**Use when** staff/admin actions, money, access changes or deletions must be
traceable — almost every project with an admin panel.

**Don't use** for analytics or tracking every page view.

Gives, on top of activitylog: redaction of sensitive keys, immutable entries,
`Actor`-aware causers, automatic logging of lifecycle transitions,
`assertActivityLogged()`.
Skill: `packages/audit/resources/boost/skills/audit-trail`.

## support — `Mgamalhz\Support`

Always installed with the others. `Actor::as($user, fn () => ...)` /
`Actor::asSystem(...)` sets "who is acting" in jobs, commands and webhooks.

---

## Not here yet — use these directly, then log in HARVEST.md

| Need | Use | What we will add later |
|---|---|---|
| Roles & permissions | `spatie/laravel-permission` | fail-closed middleware, staff invites, assertions |
| Receiving webhooks | `spatie/laravel-webhook-client` | idempotency profile |
| CSV / Excel | `spatie/simple-excel` | formula-injection guard on every cell |
| PDF (Arabic) | `mpdf/mpdf` | — |
| File uploads | `spatie/laravel-medialibrary` | private-by-default preset |
| Settings | `spatie/laravel-settings` | — |
| Phone OTP | build in the project (hash codes, expire, throttle) | `otp` package — Spatie's stores codes in plain text |
