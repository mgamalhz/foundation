<?php

namespace Mgamalhz\Lifecycle\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mgamalhz\Lifecycle\Exceptions\DirectStateMutation;
use Mgamalhz\Lifecycle\Exceptions\InvalidInitialState;
use Mgamalhz\Lifecycle\Models\StateTransition;
use Mgamalhz\Lifecycle\StateMachine;
use Mgamalhz\Lifecycle\Transitioner;
use Mgamalhz\Support\Actor;
use Mgamalhz\Support\Value;
use UnitEnum;

/**
 * Puts a model's state attribute under a declared lifecycle.
 *
 *     $order->transitionTo(OrderStatus::Paid);
 *     $order->transitionTo(OrderStatus::Cancelled, reason: 'Customer request',
 *         mutate: fn (Order $o) => $o->cancel_reason = 'customer');
 *     $order->canTransitionTo(OrderStatus::Shipped);
 *     $order->allowedTransitions();   // what the UI may offer right now
 *     $order->stateTransitions;       // the timeline
 *
 * @mixin Model
 */
trait HasLifecycle
{
    /** @internal set only while Transitioner saves this instance */
    public bool $lifecycleTransitioning = false;

    protected static int $lifecycleGuardBypass = 0;

    abstract public static function stateMachine(): StateMachine;

    public static function bootHasLifecycle(): void
    {
        static::creating(fn (Model $model) => static::startInInitialState($model));
        static::created(fn (Model $model) => static::recordBirth($model));
        static::updating(fn (Model $model) => static::preventDirectMutation($model));
    }

    /** Default a new record to the first initial state, or refuse a non-initial one. */
    protected static function startInInitialState(Model $model): void
    {
        $machine = static::stateMachine();
        $attribute = $machine->attribute();
        $initial = $machine->initialStates();

        if ($initial === []) {
            return;
        }

        if ($model->getAttribute($attribute) === null) {
            $model->setAttribute($attribute, $machine->toCase($initial[0]));

            return;
        }

        $state = Value::key($model->getAttribute($attribute));

        if (static::lifecycleIsStrict() && ! in_array($state, $initial, true)) {
            throw InvalidInitialState::make($model, $attribute, $state, $initial);
        }
    }

    protected static function recordBirth(Model $model): void
    {
        if (config('lifecycle.record_initial', true)) {
            $state = Value::key($model->getAttribute(static::stateMachine()->attribute()));

            StateTransition::record($model, null, $state, Actor::current());
        }
    }

    protected static function preventDirectMutation(Model $model): void
    {
        $attribute = static::stateMachine()->attribute();

        if ($model->isDirty($attribute) && ! $model->lifecycleTransitioning && static::lifecycleIsStrict()) {
            throw DirectStateMutation::make($model, $attribute);
        }
    }

    protected static function lifecycleIsStrict(): bool
    {
        return static::$lifecycleGuardBypass === 0 && config('lifecycle.strict', true);
    }

    /**
     * Move to `$to` atomically: lock the row, re-check the move against the
     * locked state, run guards, apply `$mutate`, save, write history and fire
     * StateTransitioned after commit. This instance is refreshed to match.
     *
     * @param  array<string, mixed>  $metadata  context stored on the history row
     * @param  (Closure(static): void)|null  $mutate  extra attribute changes saved in the same write
     * @param  Model|false|null  $actor  null = current actor, false = the system, Model = explicit
     */
    public function transitionTo(
        BackedEnum|UnitEnum|string $to,
        ?string $reason = null,
        array $metadata = [],
        ?Closure $mutate = null,
        Model|false|null $actor = null,
    ): static {
        return app(Transitioner::class)->transition($this, $to, $reason, $metadata, $mutate, $actor);
    }

    /** True when the move is declared AND every guard on it currently passes. */
    public function canTransitionTo(BackedEnum|UnitEnum|string $to): bool
    {
        $machine = static::stateMachine();

        if (! $machine->canTransition($this->getAttribute($machine->attribute()), $to)) {
            return false;
        }

        foreach ($machine->guardsFor($to) as $guard) {
            if ($guard($this) !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * States reachable right now (declared and guards passing) — the list a
     * status dropdown should offer. Enum cases when the machine uses enums.
     *
     * @return list<BackedEnum|UnitEnum|string>
     */
    public function allowedTransitions(): array
    {
        $machine = static::stateMachine();

        return collect($machine->allowedFrom($this->getAttribute($machine->attribute())))
            ->filter(fn (string $to) => $this->canTransitionTo($to))
            ->map(fn (string $to) => $machine->toCase($to))
            ->values()
            ->all();
    }

    public function isInTerminalState(): bool
    {
        $machine = static::stateMachine();

        return $machine->isTerminal($this->getAttribute($machine->attribute()));
    }

    public function stateTransitions(): MorphMany
    {
        return $this->morphMany(StateTransition::class, 'subject')->orderBy('id');
    }

    /**
     * Escape hatch for seeders, factories and one-off data fixes that must set
     * a state directly. Not for application code.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutLifecycleGuard(Closure $callback): mixed
    {
        static::$lifecycleGuardBypass++;

        try {
            return $callback();
        } finally {
            static::$lifecycleGuardBypass--;
        }
    }
}
