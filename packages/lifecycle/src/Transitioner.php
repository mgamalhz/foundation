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
        $this->assertReady($model, Value::key($to));
        $actor = Actor::resolve($actor);

        return DB::connection($model->getConnectionName())->transaction(function () use ($model, $to, $reason, $metadata, $mutate, $actor) {
            $locked = $this->lockedCopy($model);
            $from = $this->assertAllowed($model, $locked, Value::key($to));

            $this->apply($locked, $to, $mutate);

            $record = StateTransition::record($model, $from, Value::key($to), $actor, $reason, $metadata);

            // Bring the caller's instance in step with what was actually written.
            $model->setRawAttributes($locked->getAttributes(), sync: true);

            event(new StateTransitioned($model, $record->attribute, $from, $record->to, $actor, $reason, $metadata, $record));

            return $model;
        });
    }

    /** Checks that need no lock: the record is saved, clean, and the target state exists. */
    private function assertReady(Model $model, string $to): void
    {
        $name = class_basename($model);

        if (! $model->exists) {
            throw new LogicException("Save {$name} before transitioning it; new records start in an initial state.");
        }

        if ($model->isDirty()) {
            $dirty = implode(', ', array_keys($model->getDirty()));

            throw new LogicException(
                "{$name} has unsaved changes [{$dirty}]. transitionTo() writes from a freshly locked row, so they would be lost. "
                .'Save them first, or set them inside the transition: transitionTo($state, mutate: fn ($m) => $m->field = $value).'
            );
        }

        $machine = $model::stateMachine();

        if (! $machine->hasState($to)) {
            throw InvalidTransition::unknownState($model, $machine->attribute(), $to, $machine->states());
        }
    }

    /** @return Model&HasLifecycle */
    private function lockedCopy(Model $model): Model
    {
        return $model->newQueryWithoutScopes()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Validate the move against the locked (current) state and run guards.
     *
     * @return string|null the state being left
     */
    private function assertAllowed(Model $model, Model $locked, string $to): ?string
    {
        $machine = $model::stateMachine();
        $attribute = $machine->attribute();
        $from = Value::key($locked->getAttribute($attribute));

        if (! $machine->canTransition($from, $to)) {
            throw InvalidTransition::between($model, $attribute, $from, $to, $machine->allowedFrom($from));
        }

        foreach ($machine->guardsFor($to) as $guard) {
            $result = $guard($locked);

            if ($result !== true) {
                throw TransitionDenied::make($model, $attribute, $to, is_string($result) ? $result : null);
            }
        }

        return $from;
    }

    /** @param  Model&HasLifecycle  $locked */
    private function apply(Model $locked, BackedEnum|UnitEnum|string $to, ?Closure $mutate): void
    {
        $locked->setAttribute($locked::stateMachine()->attribute(), $to);

        if ($mutate !== null) {
            $mutate($locked);
        }

        $locked->lifecycleTransitioning = true;

        try {
            $locked->save();
        } finally {
            $locked->lifecycleTransitioning = false;
        }
    }
}
