<?php

namespace Mgamalhz\Lifecycle\Testing;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Mgamalhz\Lifecycle\Models\StateTransition;
use Mgamalhz\Support\Value;
use PHPUnit\Framework\Assert;
use UnitEnum;

/**
 *     $this->assertTransitioned($order, from: OrderStatus::Paid, to: OrderStatus::Shipped, by: $admin);
 *     $this->assertCanTransition(Order::class, OrderStatus::Paid, OrderStatus::Refunded);
 *     $this->assertCannotTransition(Order::class, OrderStatus::Delivered, OrderStatus::Cancelled);
 */
trait InteractsWithLifecycle
{
    public function assertTransitioned(
        Model $model,
        BackedEnum|UnitEnum|string|null $from,
        BackedEnum|UnitEnum|string $to,
        ?Model $by = null,
    ): StateTransition {
        $query = StateTransition::query()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->where('to', Value::key($to));

        $from === null ? $query->whereNull('from') : $query->where('from', Value::key($from));

        if ($by !== null) {
            $query->where('actor_type', $by->getMorphClass())->where('actor_id', $by->getKey());
        }

        $record = $query->latest('id')->first();

        $timeline = StateTransition::query()
            ->where('subject_type', $model->getMorphClass())
            ->where('subject_id', $model->getKey())
            ->orderBy('id')
            ->get()
            ->map(fn (StateTransition $t) => ($t->from ?? '∅').' → '.$t->to)
            ->implode(', ') ?: 'no transitions';

        Assert::assertNotNull(
            $record,
            sprintf('Expected %s to move [%s → %s]. Actual timeline: %s.', class_basename($model), Value::key($from) ?? '∅', Value::key($to), $timeline)
        );

        return $record;
    }

    /** @param class-string|Model $model */
    public function assertCanTransition(string|Model $model, BackedEnum|UnitEnum|string $from, BackedEnum|UnitEnum|string $to): void
    {
        $machine = (is_string($model) ? $model : $model::class)::stateMachine();

        Assert::assertTrue(
            $machine->canTransition($from, $to),
            sprintf('Expected [%s → %s] to be declared. Allowed from [%s]: %s.', Value::key($from), Value::key($to), Value::key($from), implode(', ', $machine->allowedFrom($from)) ?: 'none')
        );
    }

    /** @param class-string|Model $model */
    public function assertCannotTransition(string|Model $model, BackedEnum|UnitEnum|string $from, BackedEnum|UnitEnum|string $to): void
    {
        $machine = (is_string($model) ? $model : $model::class)::stateMachine();

        Assert::assertFalse(
            $machine->canTransition($from, $to),
            sprintf('Expected [%s → %s] to be forbidden, but it is declared.', Value::key($from), Value::key($to))
        );
    }
}
