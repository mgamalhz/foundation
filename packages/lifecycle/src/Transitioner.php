<?php

namespace Mgamalhz\Lifecycle;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;
use Mgamalhz\Lifecycle\Concerns\HasLifecycle;
use Mgamalhz\Lifecycle\Events\StateTransitioned;
use Mgamalhz\Lifecycle\Exceptions\InvalidTransition;
use Mgamalhz\Lifecycle\Exceptions\TransitionDenied;
use Mgamalhz\Lifecycle\Models\StateTransition;
use Mgamalhz\Support\Actor;
use Mgamalhz\Support\Value;
use UnitEnum;

/**
 * The only code that changes a lifecycle-managed attribute.
 *
 * Why the lock: two requests (a customer cancelling while a driver starts the
 * job) can both pass a check made on their own stale copy of the row. Re-reading
 * under `lockForUpdate()` and validating against *that* makes the loser fail
 * with InvalidTransition instead of silently overwriting the winner.
 */
class Transitioner
{
    /**
     * @param  Model&HasLifecycle  $model
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        Model $model,
        BackedEnum|UnitEnum|string $to,
        ?string $reason = null,
        array $metadata = [],
        ?Closure $mutate = null,
        Model|false|null $actor = null,
    ): Model {
        $machine = $model::stateMachine();
        $attribute = $machine->attribute();
        $toKey = Value::key($to);

        if (! $model->exists) {
            throw new LogicException('Save '.class_basename($model).' before transitioning it; new records start in an initial state.');
        }

        if ($model->isDirty()) {
            $dirty = implode(', ', array_keys($model->getDirty()));

            throw new LogicException(
                class_basename($model)." has unsaved changes [{$dirty}]. transitionTo() writes from a freshly locked row, so they would be lost. "
                .'Save them first, or set them inside the transition: transitionTo($state, mutate: fn ($m) => $m->field = $value).'
            );
        }

        if (! $machine->hasState($toKey)) {
            throw InvalidTransition::unknownState($model, $attribute, $toKey, $machine->states());
        }

        $actor = Actor::resolve($actor);

        return DB::connection($model->getConnectionName())->transaction(function () use ($model, $machine, $attribute, $to, $toKey, $reason, $metadata, $mutate, $actor) {
            /** @var Model&HasLifecycle $locked */
            $locked = $model->newQueryWithoutScopes()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = Value::key($locked->getAttribute($attribute));

            if (! $machine->canTransition($from, $toKey)) {
                throw InvalidTransition::between($model, $attribute, $from, $toKey, $machine->allowedFrom($from));
            }

            foreach ($machine->guardsFor($toKey) as $guard) {
                $result = $guard($locked);

                if ($result !== true) {
                    throw TransitionDenied::make($model, $attribute, $toKey, is_string($result) ? $result : null);
                }
            }

            $locked->setAttribute($attribute, $to);

            if ($mutate !== null) {
                $mutate($locked);
            }

            $locked->lifecycleTransitioning = true;

            try {
                $locked->save();
            } finally {
                $locked->lifecycleTransitioning = false;
            }

            $record = StateTransition::query()->create([
                'subject_type' => $model->getMorphClass(),
                'subject_id' => $model->getKey(),
                'attribute' => $attribute,
                'from' => $from,
                'to' => $toKey,
                'actor_type' => $actor?->getMorphClass(),
                'actor_id' => $actor?->getKey(),
                'reason' => $reason,
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);

            // Bring the caller's instance in step with what was actually written.
            $model->setRawAttributes($locked->getAttributes(), sync: true);

            event(new StateTransitioned($model, $attribute, $from, $toKey, $actor, $reason, $metadata, $record));

            return $model;
        });
    }
}
