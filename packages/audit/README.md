# mgamalhz/audit

One immutable activity log for the whole app: who did what to which record, with before/after, redaction and test assertions. Ships an agent skill for Laravel Boost.

```bash
composer require mgamalhz/audit
php artisan migrate
```

See the [main README](../../README.md) and the agent skill in `resources/boost/skills/audit-trail/SKILL.md`.
