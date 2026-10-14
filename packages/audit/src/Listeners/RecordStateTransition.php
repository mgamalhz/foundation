<?php

namespace Mgamalhz\Audit\Listeners;

use Mgamalhz\Audit\Auditor;

/**
 * Mirrors lifecycle transitions into the audit log (see `audit.record_transitions`).
 * Typed loosely on purpose: this package must not require mgamalhz/lifecycle.
 */
class RecordStateTransition
{
    public function __construct(private readonly Auditor $auditor) {}

    public function handle(object $event): void
    {
        if (! config('audit.record_transitions', true)) {
            return;
        }

        $this->auditor->record(
            action: 'lifecycle.transitioned',
            subject: $event->model,
            metadata: array_filter([
                'attribute' => $event->attribute,
                'from' => $event->from,
                'to' => $event->to,
                'reason' => $event->reason,
            ], fn ($value) => $value !== null) + ($event->metadata === [] ? [] : ['context' => $event->metadata]),
            actor: $event->actor ?? false,
        );
    }
}
