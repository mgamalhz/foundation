<?php

namespace Mgamalhz\Lifecycle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One row per change of state — the timeline of a record. Immutable.
 *
 * @property string $attribute
 * @property string|null $from
 * @property string $to
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 */
class StateTransition extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('lifecycle.table', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('State transitions are history and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('State transitions are history and cannot be deleted.'));
    }

    /**
     * The single place a history row is written.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function record(Model $subject, ?string $from, string $to, ?Model $actor, ?string $reason = null, array $metadata = []): self
    {
        return static::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'attribute' => $subject::stateMachine()->attribute(),
            'from' => $from,
            'to' => $to,
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
            'created_at' => now(),
        ]);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
