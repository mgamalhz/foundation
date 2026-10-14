<?php

namespace Mgamalhz\Audit;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Mgamalhz\Audit\Models\AuditLog;
use Mgamalhz\Support\Actor;
use Mgamalhz\Support\Value;

/**
 * The single entry point for writing to the audit log, so every entry has the
 * same shape no matter which feature wrote it.
 */
class Auditor
{
    /**
     * @param  string  $action  dot.notation, past tense: "order.refunded", "staff.invited"
     * @param  array<string, mixed>  $metadata  context worth keeping (reason, amount, ...)
     * @param  array{before?: array<string, mixed>, after?: array<string, mixed>}  $changes
     * @param  Model|false|null  $actor  null = current actor, false = the system, Model = explicit
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        array $changes = [],
        Model|false|null $actor = null,
    ): AuditLog {
        if (! preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $action)) {
            throw new InvalidArgumentException(
                "Audit action [{$action}] must be lowercase dot.notation with at least two segments, e.g. \"order.refunded\"."
            );
        }

        if ($subject !== null && ! $subject->exists) {
            throw new InvalidArgumentException(
                'Audit subject ['.$subject::class.'] has not been saved yet. Record the audit entry after saving the model.'
            );
        }

        $actor = Actor::resolve($actor);
        $redact = config('audit.redact', []);

        return AuditLog::query()->create([
            'action' => $action,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes === [] ? null : Value::redact($changes, $redact),
            'metadata' => $metadata === [] ? null : Value::redact($metadata, $redact),
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * The before/after of the attributes changed by the model's last save.
     * Call it right after `$model->save()` / `update()`.
     *
     * @param  list<string>|null  $only  limit to these attributes
     * @return array{before: array<string, mixed>, after: array<string, mixed>}
     */
    public function changesOf(Model $model, ?array $only = null): array
    {
        $after = collect($model->getChanges())->except([$model->getUpdatedAtColumn()]);

        if ($only !== null) {
            $after = $after->only($only);
        }

        $before = collect($model->getPrevious())->only($after->keys());

        return ['before' => $before->all(), 'after' => $after->all()];
    }
}
