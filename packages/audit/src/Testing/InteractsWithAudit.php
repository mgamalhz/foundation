<?php

namespace Mgamalhz\Audit\Testing;

use Illuminate\Database\Eloquent\Model;
use Mgamalhz\Audit\Models\AuditLog;
use PHPUnit\Framework\Assert;

/**
 * Assertions for tests that change something an admin must be able to trace.
 *
 *     $this->assertAudited($order, 'order.refunded', by: $admin, metadata: ['reason' => 'damaged']);
 */
trait InteractsWithAudit
{
    /**
     * @param  array<string, mixed>|null  $metadata  subset that must be present in the entry's metadata
     */
    public function assertAudited(?Model $subject, string $action, Model|false|null $by = null, ?array $metadata = null): AuditLog
    {
        $query = AuditLog::query()->where('action', $action);

        if ($subject !== null) {
            $query->forSubject($subject);
        }

        if ($by instanceof Model) {
            $query->byActor($by);
        } elseif ($by === false) {
            $query->whereNull('actor_id');
        }

        $entries = $query->get();

        if ($metadata !== null) {
            $entries = $entries->filter(fn (AuditLog $log) => array_intersect_key($log->metadata ?? [], $metadata) == $metadata);
        }

        $recorded = AuditLog::query()->pluck('action')->unique()->implode(', ') ?: 'none';

        Assert::assertNotEmpty(
            $entries,
            "Expected an audit entry [{$action}] but none matched. Actions recorded in this test: {$recorded}."
        );

        return $entries->last();
    }

    public function assertNotAudited(?Model $subject, string $action): void
    {
        $query = AuditLog::query()->where('action', $action);

        if ($subject !== null) {
            $query->forSubject($subject);
        }

        Assert::assertSame(0, $query->count(), "Expected no audit entry [{$action}], but one was recorded.");
    }

    public function assertAuditCount(int $expected, ?string $action = null): void
    {
        $query = AuditLog::query();

        if ($action !== null) {
            $query->where('action', $action);
        }

        Assert::assertSame($expected, $query->count());
    }
}
