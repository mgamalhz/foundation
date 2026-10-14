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

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }
}
