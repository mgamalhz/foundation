<?php

namespace Mgamalhz\Audit\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * An audit entry is a fact about the past: it is written once and never
 * changed or removed through the application.
 *
 * @property string $action
 * @property array<string, mixed>|null $changes
 * @property array<string, mixed>|null $metadata
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('audit.table', parent::getTable());
    }

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable and cannot be updated.'));
        static::deleting(fn () => throw new LogicException('Audit log entries are immutable and cannot be deleted.'));
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForSubject(Builder $query, Model $subject): void
    {
        $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeByActor(Builder $query, Model $actor): void
    {
        $query->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey());
    }

    public function scopeAction(Builder $query, string ...$actions): void
    {
        $query->whereIn('action', $actions);
    }
}
