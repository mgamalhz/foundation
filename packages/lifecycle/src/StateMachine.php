<?php

namespace Mgamalhz\Lifecycle;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Mgamalhz\Support\Value;
use UnitEnum;

/**
 * The whole lifecycle of a model in one readable table. Anything not declared
 * here is rejected — adding a business rule means adding a row, never a branch
 * somewhere else in the codebase.
 *
 *     StateMachine::for('status')
 *         ->initial(OrderStatus::Pending)
 *         ->allow(OrderStatus::Pending, [OrderStatus::Paid, OrderStatus::Cancelled])
 *         ->allow(OrderStatus::Paid, [OrderStatus::Shipped, OrderStatus::Refunded])
 *         ->guard(OrderStatus::Refunded, fn (Order $o) => $o->total > 0 ?: 'Nothing to refund.');
 */
final class StateMachine
{
    /** @var array<string, list<string>> from => [to, ...] */
    private array $transitions = [];

    /** @var list<string> */
    private array $initial = [];

    /** @var array<string, list<Closure(Model): (bool|string)>> */
    private array $guards = [];

    /** @var class-string<UnitEnum>|null */
    private ?string $enum = null;

    private function __construct(private readonly string $attribute) {}

    public static function for(string $attribute): self
    {
        return new self($attribute);
    }

    /** States a new record may be created in. The first is used when none is given. */
    public function initial(BackedEnum|UnitEnum|string ...$states): self
    {
        foreach ($states as $state) {
            $this->initial[] = $this->remember($state);
        }

        return $this;
    }

    /**
     * Declare legal moves. Calling it again for the same `$from` adds to the list.
     * A state listed in its own `$to` is a legal self-transition (e.g. reassignment).
     *
     * @param  BackedEnum|UnitEnum|string|array<BackedEnum|UnitEnum|string>  $to
     */
    public function allow(BackedEnum|UnitEnum|string $from, BackedEnum|UnitEnum|string|array $to): self
    {
        $from = $this->remember($from);
        $this->transitions[$from] ??= [];

        foreach ((array) $to as $target) {
            $target = $this->remember($target);
            $this->transitions[$target] ??= [];

            if (! in_array($target, $this->transitions[$from], true)) {
                $this->transitions[$from][] = $target;
            }
        }

        return $this;
    }

    /**
     * A business condition for entering `$to`. The check receives the row-locked
     * model and returns `true` to allow, or `false` / a human-readable reason to deny.
     *
     * @param  Closure(Model): (bool|string)  $check
     */
    public function guard(BackedEnum|UnitEnum|string $to, Closure $check): self
    {
        $this->guards[$this->remember($to)][] = $check;

        return $this;
    }

    public function attribute(): string
    {
        return $this->attribute;
    }

    /** @return class-string<UnitEnum>|null */
    public function enum(): ?string
    {
        return $this->enum;
    }

    public function canTransition(BackedEnum|UnitEnum|string|null $from, BackedEnum|UnitEnum|string $to): bool
    {
        return in_array(Value::key($to), $this->transitions[Value::key($from)] ?? [], true);
    }

    /** @return list<string> */
    public function allowedFrom(BackedEnum|UnitEnum|string|null $from): array
    {
        return $this->transitions[Value::key($from)] ?? [];
    }

    /** @return list<string> */
    public function states(): array
    {
        return array_values(array_unique([...$this->initial, ...array_keys($this->transitions)]));
    }

    /** @return list<string> */
    public function initialStates(): array
    {
        return $this->initial;
    }

    public function isTerminal(BackedEnum|UnitEnum|string $state): bool
    {
        return ($this->transitions[Value::key($state)] ?? []) === [];
    }

    public function hasState(BackedEnum|UnitEnum|string|null $state): bool
    {
        return $state !== null && in_array(Value::key($state), $this->states(), true);
    }

    /** @return list<Closure(Model): (bool|string)> */
    public function guardsFor(BackedEnum|UnitEnum|string $to): array
    {
        return $this->guards[Value::key($to)] ?? [];
    }

    /** Turn a stored key back into the enum case when the machine was declared with enums. */
    public function toCase(string $key): BackedEnum|UnitEnum|string
    {
        if ($this->enum === null) {
            return $key;
        }

        if (is_subclass_of($this->enum, BackedEnum::class)) {
            return ($this->enum)::from(is_numeric($key) ? (int) $key : $key);
        }

        return constant($this->enum.'::'.$key);
    }

    /** A Mermaid stateDiagram of the lifecycle, for docs and PR descriptions. */
    public function toMermaid(): string
    {
        $lines = ['stateDiagram-v2'];

        foreach ($this->initial as $state) {
            $lines[] = "    [*] --> {$state}";
        }

        foreach ($this->transitions as $from => $targets) {
            foreach ($targets as $to) {
                $label = isset($this->guards[$to]) ? ' : guarded' : '';
                $lines[] = "    {$from} --> {$to}{$label}";
            }

            if ($targets === []) {
                $lines[] = "    {$from} --> [*]";
            }
        }

        return implode("\n", $lines);
    }

    private function remember(BackedEnum|UnitEnum|string $state): string
    {
        if ($state instanceof UnitEnum) {
            $this->enum ??= $state::class;
        }

        return Value::key($state);
    }
}
