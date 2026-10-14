---
name: audit-trail
description: Use when a change must be traceable — staff/admin actions, money, permissions, approvals, exports, deletions; anything a manager or a dispute may later ask "who did this and when?". Covers logging with spatie/laravel-activitylog plus this app's rules (redaction, immutability, actors in jobs) and test assertions.
---

# Audit trail (spatie/laravel-activitylog + mgamalhz/foundation)

This app has **one** activity log. Do not create feature-specific log tables
(`order_logs`, `admin_activities`, ...). Use activitylog's own API.

## When to log

- staff/admin actions: approve, reject, refund, ban, change role, export data
- money: charges, refunds, payouts, price or discount changes
- access: invites, role/permission changes, staff-initiated password resets
- deletions of business records

Do **not** log reads or every model save. Lifecycle state changes are logged
automatically (`event = transitioned`, log `lifecycle`) — don't log them again.

## How

```php
activity()
    ->performedOn($order)
    ->event('refunded')                               // machine-readable, snake_case past tense
    ->withProperties(['amount' => $amount, 'reason' => $reason])
    ->log('Order refunded');                          // human-readable, may be Arabic
```

- Log **after** the change is saved, inside the same transaction.
- The causer is the authenticated user. In jobs, commands and webhooks set it
  with `Actor::as($admin, fn () => ...)`; when the system itself acts use
  `Actor::asSystem(fn () => ...)` or `->causedByAnonymous()`.
- `properties` is context (reason, amount, external reference), not a copy of
  the model. Keys like `password`, `token`, `otp`, `iban`, `cvv` are redacted
  automatically; never put card numbers or secrets there.
- Entries are immutable: updating or deleting one throws. Retention is handled
  by `php artisan activitylog:clean`.

## Reading

```php
Activity::forSubject($order)->latest()->get();
Activity::causedBy($staff)->latest()->paginate();
Activity::forEvent('refunded')->get();
```

## Testing — required for every logged action

```php
use Mgamalhz\Audit\Testing\InteractsWithAudit;

uses(InteractsWithAudit::class);

it('logs refunds', function () {
    $this->actingAs($admin)->post("/admin/orders/{$order->id}/refund", ['reason' => 'damaged']);

    $this->assertActivityLogged($order, 'refunded', by: $admin, properties: ['reason' => 'damaged']);
});
```

## Done checklist

- [ ] Every new staff/money/access action logs one activity with a snake_case `event`
- [ ] Jobs and commands set the actor with `Actor::as(...)`
- [ ] No new feature-specific log tables
- [ ] Tests assert it with `assertActivityLogged`
