# Foundation

The base every new backend project starts from.

**Goals:** less time building, fewer vulnerabilities.

> Personal `0.x` release. The API may change.

## What's in it

| Part | What it does | Built on |
|---|---|---|
| `lifecycle` | Model states (pending → paid → …) with row locks, guards and history | Our own code |
| `audit` | Additions to the activity log: redaction, immutability, transition logging, assertions | `spatie/laravel-activitylog` |
| `support` | `Actor`: who is acting, in a request or a job | Our own code |

When to use each part, and when not to: [CATALOG.md](CATALOG.md).

## Install in a project

```json
"repositories": [{ "type": "vcs", "url": "git@github.com:mgamalhz/foundation.git" }],
"require": { "mgamalhz/foundation": "0.1.*" }
```

```bash
composer update mgamalhz/foundation
php artisan migrate
# For audit:
composer require spatie/laravel-activitylog
php artisan vendor:publish --tag="activitylog-migrations" && php artisan migrate
```

## Quick examples

```php
// lifecycle
$order->transitionTo(OrderStatus::Paid, reason: 'Card captured');
$order->allowedTransitions();   // what the UI may offer right now
$order->stateTransitions;       // the history

// audit (activitylog's own API)
activity()->performedOn($order)->event('refunded')->withProperties(['reason' => $r])->log('Order refunded');

// in jobs and commands
Actor::as($admin, fn () => $order->transitionTo(OrderStatus::Refunded));
```

`php artisan lifecycle:show Order` prints the real rules of any model.

## Rules

1. **Build on proven libraries.** Write from scratch only where there is a real gap.
2. **Extend, don't wrap.** Code keeps using `activity()` and `$user->can()` as they are.
3. **Repeated work:** first time, log it in [HARVEST.md](HARVEST.md); second time, it moves here — rewritten, never copied from a client project.
4. **Behaviour changes** update the part's skill and tests in the same commit.
5. **Ideas** go in [IDEAS.md](IDEAS.md) and enter the code only when a real project needs them.
6. **English only** in code, docs and commits.

## For AI agents

Each part ships a skill in `packages/*/resources/boost/skills/`. With Laravel Boost, `boost:install` installs them automatically.

## Development

```bash
composer install
vendor/bin/pest     # must stay green
vendor/bin/pint
```

Requires PHP 8.4 and Laravel 13.
