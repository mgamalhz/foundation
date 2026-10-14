<?php

namespace Mgamalhz\Audit\Listeners;

/**
 * Logs lifecycle transitions to the activity log (see `audit.record_transitions`).
 * Typed loosely on purpose: the audit additions must not require the lifecycle package.
 */
class LogStateTransition
{
    public function handle(object $event): void
    {
        if (! config('audit.record_transitions', true)) {
            return;
        }

        $logger = activity('lifecycle')
            ->performedOn($event->model)
            ->event('transitioned')
            ->withProperties(array_filter([
                'attribute' => $event->attribute,
                'from' => $event->from,
                'to' => $event->to,
                'reason' => $event->reason,
                'context' => $event->metadata ?: null,
            ], fn ($value) => $value !== null));

        $event->actor ? $logger->causedBy($event->actor) : $logger->causedByAnonymous();

        $logger->log("{$event->attribute}: {$event->from} → {$event->to}");
    }
}
