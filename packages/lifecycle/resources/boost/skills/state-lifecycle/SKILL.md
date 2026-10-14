---
name: state-lifecycle
description: Use when adding, changing or reading a model's status/state (orders, bookings, requests, subscriptions, invoices, tickets) — any column whose value moves through steps like pending → paid → shipped. Covers declaring transitions, changing state safely, guards, history, events and tests with mgamalhz/lifecycle.
---

# State lifecycle (mgamalhz/lifecycle)

A status column is a business process, not a string. In this app every status
column that moves through steps is owned by a **declared state machine**, and
the only way to change it is `transitionTo()`.

## Before you change anything

Run `php artisan lifecycle:show <Model>` to see the real states and allowed
moves. Do not guess them from the enum — the enum lists states, not rules.

## Rules

1. **Never assign the state attribute directly.** `$order->status = ...; $order->save()`
   and `$order->update(['status' => ...])` throw `DirectStateMutation`. Use:
   ```php
   $order->transitionTo(OrderStatus::Paid);
   ```
2. **A new rule is a new row in `stateMachine()`, never an `if` somewhere else.**
   If a move is needed and not declared, add it to the model's `stateMachine()`
   and say so in your summary — it is a business decision the human must see.
3. **Conditions belong in guards**, not in controllers:
   ```php
   ->guard(OrderStatus::Refunded, fn (Order $o) => $o->total > 0 ?: 'Nothing was charged.')
   ```
   Return `true` to allow, or a sentence a user can read to deny.
4. **Changes that must happen together with the move go in `mutate`**, so they
   are saved in the same locked write:
   ```php
   $order->transitionTo(OrderStatus::Cancelled, reason: $request->reason,
       mutate: fn (Order $o) => $o->cancelled_at = now());
   ```
5. **Side effects (emails, webhooks, notifications) listen to `StateTransitioned`.**
   It fires only after the transaction commits, so rolled-back moves never notify anyone.
   ```php
   Event::listen(function (StateTransitioned $e) {
       if ($e->is(Order::class, OrderStatus::Shipped)) { /* ... */ }
   });
   ```
6. **Who did it** is recorded automatically from the authenticated user. In jobs,
   commands and webhooks, say it explicitly:
   `Actor::as($admin, fn () => ...)` or `actor: false` for the system.
7. **UI status pickers** must offer `$model->allowedTransitions()` — never the full enum.
8. Seeders and factories that need a record in a non-initial state wrap creation in
   `Order::withoutLifecycleGuard(fn () => ...)`. Application code never does.

## Adding a lifecycle to a model

```php
use Mgamalhz\Lifecycle\Concerns\HasLifecycle;
use Mgamalhz\Lifecycle\StateMachine;

class Booking extends Model
{
    use HasLifecycle;

    protected function casts(): array
    {
        return ['status' => BookingStatus::class];   // a string-backed enum
    }

    public static function stateMachine(): StateMachine
    {
        return StateMachine::for('status')
            ->initial(BookingStatus::Requested)
            ->allow(BookingStatus::Requested, [BookingStatus::Confirmed, BookingStatus::Cancelled])
            ->allow(BookingStatus::Confirmed, [BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow]);
    }
}
```

Terminal states are the ones with no `allow()` row of their own. A state listed
in its own targets is a legal self-transition (e.g. reassignment).

## Handling failures in controllers

| Exception | Meaning | Respond with |
|---|---|---|
| `InvalidTransition` | move not declared, or someone changed the state first | 422 / 409 with the message |
| `TransitionDenied` | a guard said no; `$e->reason` is user-readable | 422 with `$e->reason` |
| `DirectStateMutation` | a bug: the state was assigned directly | fix the code |

## Testing — required for every new or changed transition

```php
use Mgamalhz\Lifecycle\Testing\InteractsWithLifecycle;

uses(InteractsWithLifecycle::class);

it('ships a paid order', function () {
    $order = Order::factory()->create();
    $order->transitionTo(OrderStatus::Paid);

    $this->actingAs($admin)->post("/admin/orders/{$order->id}/ship")->assertOk();

    $this->assertTransitioned($order, from: OrderStatus::Paid, to: OrderStatus::Shipped, by: $admin);
});

it('never cancels a delivered order', function () {
    $this->assertCannotTransition(Order::class, OrderStatus::Delivered, OrderStatus::Cancelled);
});
```

Test the guard's denial path too, not only the happy path.

## Done checklist

- [ ] No direct assignment of the state attribute anywhere (`grep -n "status' =>" app/`)
- [ ] Every new move declared in `stateMachine()` and mentioned in your summary
- [ ] Business conditions are guards with readable reasons
- [ ] Side effects listen to `StateTransitioned`, not inline after `transitionTo()`
- [ ] Tests use `assertTransitioned` / `assertCannotTransition`
