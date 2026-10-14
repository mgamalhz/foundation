---
name: audit-trail
description: Use when a change must be traceable — admin/staff actions, money, permissions, approvals, data exports, deletions, anything a manager or a dispute might later ask "who did this and when?". Covers recording entries, before/after changes, actors in jobs, redaction and tests with mgamalhz/audit.
---

# Audit trail (mgamalhz/audit)

This app has **one** audit log for everything. Do not create feature-specific
log tables (`order_logs`, `admin_activities`, ...) — record here.

## When to record

Record when a human (or the system on their behalf) does something someone may
need to trace later:

- staff/admin actions: approve, reject, refund, ban, change role, export data
- money: charges, refunds, payouts, price or discount changes
- access: invites, role/permission changes, password resets by staff
- deletions of business records

Do **not** audit every read or every model save. State changes of models using
`HasLifecycle` are audited automatically as `lifecycle.transitioned` — don't
record them again.

## How

```php
use Mgamalhz\Audit\Facades\Audit;

// After the change is saved:
Audit::record('order.refunded', $order, ['amount' => $amount, 'reason' => $reason]);
```

On models with the `HasAuditTrail` trait:

```php
$order->update(['total' => $newTotal]);
$order->audit('order.repriced', ['reason' => $reason], withChanges: true); // stores before/after
$order->auditTrail;                                                       // newest first
```

## Rules

1. **Action names**: lowercase `subject.past_tense_verb` — `order.refunded`,
   `staff.invited`, `payout.approved`. Anything else throws.
2. **Record after saving**, inside the same transaction as the change, so a
   rolled-back change leaves no entry. Unsaved subjects throw.
3. **The actor** comes from the authenticated user. In jobs, commands and
   webhooks pass it explicitly — `Actor::as($admin, fn () => ...)` — or
   `actor: false` when the system itself acts.
4. **Metadata is context, not a copy of the model**: reason, amount, external
   reference. Keys like `password`, `token`, `otp`, `iban` are redacted
   automatically; never put full card numbers or secrets in metadata.
5. Entries are immutable. Never update or delete them.

## Showing it

```php
AuditLog::query()->forSubject($order)->latest('id')->get();   // a record's history
AuditLog::query()->byActor($staff)->latest('id')->paginate();  // what a staff member did
AuditLog::query()->action('order.refunded')->get();
```

## Testing — required for every audited action

```php
use Mgamalhz\Audit\Testing\InteractsWithAudit;

uses(InteractsWithAudit::class);

it('audits refunds', function () {
    $this->actingAs($admin)->post("/admin/orders/{$order->id}/refund", ['reason' => 'damaged']);

    $this->assertAudited($order, 'order.refunded', by: $admin, metadata: ['reason' => 'damaged']);
});
```

## Done checklist

- [ ] Every new staff/money/access action records one entry with a `subject.verb` name
- [ ] Jobs and commands set the actor explicitly
- [ ] No new feature-specific log tables
- [ ] Tests assert the entry with `assertAudited`
