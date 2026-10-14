<?php

namespace Mgamalhz\Lifecycle\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Mgamalhz\Lifecycle\Models\StateTransition;

/**
 * Fired after the transaction that made the transition commits, so listeners
 * (notifications, webhooks, audit) never react to a change that was rolled back.
 *
 * Listen for one model and state:
 *
 *     Event::listen(function (StateTransitioned $e) {
 *         if ($e->model instanceof Order && $e->to === OrderStatus::Shipped->value) { ... }
 *     });
 */
final class StateTransitioned implements ShouldDispatchAfterCommit
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly Model $model,
        public readonly string $attribute,
        public readonly ?string $from,
        public readonly string $to,
        public readonly ?Model $actor,
        public readonly ?string $reason,
        public readonly array $metadata,
        public readonly StateTransition $transition,
    ) {}

    public function is(string $modelClass, string|\BackedEnum $to): bool
    {
        return $this->model instanceof $modelClass
            && $this->to === ($to instanceof \BackedEnum ? (string) $to->value : $to);
    }
}
