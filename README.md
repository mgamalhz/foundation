# Foundation

> **Status: 0.x, being built in the open.** APIs may change between minor versions until 1.0.

Backend building blocks for business platforms on Laravel — the parts every
order, booking or marketplace system rebuilds from scratch, done once and done
properly. Each package works on its own, and each ships an **agent skill** so a
coding agent (Claude Code, Cursor, Codex…) builds on it correctly instead of
re-inventing it.

| Package | What it gives you |
|---|---|
| [`mgamalhz/lifecycle`](packages/lifecycle) | State machines for Eloquent models: declared transitions, row-locked changes, guards, full history, after-commit events |
| [`mgamalhz/audit`](packages/audit) | One immutable activity log: who did what to which record, before/after, redaction |
| [`mgamalhz/support`](packages/support) | Shared pieces: resolving "who is acting" in requests, jobs and commands |

Requires PHP 8.3+ and Laravel 13.

## Why

Across real projects the same things get rebuilt every time, slightly differently:
a status column changed from five places, a `*_status_histories` table per feature,
an `admin_activities` table here and an `audit_logs` table there. An AI agent asked
to "add refunds" invents yet another variant. These packages make the right way the
easy way — and the only way an agent will reach for.

## Lifecycle in 30 seconds

```php
class Order extends Model
{
    use HasLifecycle;

    public static function stateMachine(): StateMachine
    {
        return StateMachine::for('status')
            ->initial(OrderStatus::Pending)
            ->allow(OrderStatus::Pending, [OrderStatus::Paid, OrderStatus::Cancelled])
            ->allow(OrderStatus::Paid, [OrderStatus::Shipped, OrderStatus::Refunded])
            ->allow(OrderStatus::Shipped, [OrderStatus::Delivered])
            ->guard(OrderStatus::Refunded, fn (Order $o) => $o->total > 0 ?: 'Nothing was charged.');
    }
}

$order->transitionTo(OrderStatus::Paid, reason: 'Card captured');
$order->allowedTransitions();   // what a status dropdown may offer right now
$order->stateTransitions;       // the timeline
```

What you get for free:

- **Row lock + re-check** — a late payment callback can't "pay" an order the customer already cancelled.
- **Direct assignment is a bug** — `$order->update(['status' => ...])` throws, with a message saying what to do instead.
- **History** of every move, with actor, reason and metadata.
- **`StateTransitioned` after commit** — rolled-back moves never send emails.
- **Errors that explain themselves** — `Allowed from [pending]: paid, cancelled`.
- `php artisan lifecycle:show Order [--mermaid]` — the real rules, for humans and agents.

## Audit in 30 seconds

```php
Audit::record('order.refunded', $order, ['amount' => 250, 'reason' => 'damaged']);

$order->update(['total' => 900]);
$order->audit('order.repriced', ['reason' => $reason], withChanges: true);  // before/after
```

With both packages installed, every lifecycle transition is also written to the
audit log as `lifecycle.transitioned` — one activity feed, no extra code.

## Who is acting

Both packages record the actor automatically from the authenticated user. Jobs,
commands and webhooks say it explicitly:

```php
Actor::as($admin, fn () => $order->transitionTo(OrderStatus::Refunded));
Actor::asSystem(fn () => $subscription->transitionTo(Status::Expired));
```

## Built for coding agents

Each package ships a skill in `resources/boost/skills/`. With
[Laravel Boost](https://laravel.com/docs/boost), `php artisan boost:install`
picks them up automatically. Without Boost, copy the skill folders into your
agent's skills directory (e.g. `.claude/skills/`). The skills tell the agent when
to use the package, the rules it must not break, and the tests it must write —
and the packages back those rules with exceptions and test assertions:

```php
$this->assertTransitioned($order, from: OrderStatus::Paid, to: OrderStatus::Shipped, by: $admin);
$this->assertCannotTransition(Order::class, OrderStatus::Delivered, OrderStatus::Cancelled);
$this->assertAudited($order, 'order.refunded', by: $admin, metadata: ['reason' => 'damaged']);
```

## Development

```bash
composer install
vendor/bin/pest      # all packages
vendor/bin/pint      # formatting
```

## Roadmap

- [x] `lifecycle` — state machines with locking, guards, history, events
- [x] `audit` — immutable activity log
- [ ] Concurrency tests against PostgreSQL in CI (row locks are a no-op on SQLite)
- [ ] `access` — staff, roles, permissions, invitations
- [ ] `@mgamalhz/admin-ui` — React components: `<StatusTimeline />`, `<AuditLog />`, `<RolesMatrix />`
- [ ] `settings`, `otp`, `exports`

## License

MIT
