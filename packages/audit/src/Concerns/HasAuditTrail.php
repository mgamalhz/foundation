<?php

namespace Mgamalhz\Audit\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mgamalhz\Audit\Auditor;
use Mgamalhz\Audit\Models\AuditLog;

/**
 * Gives a model its own audit trail:
 *
 *     $order->update(['total' => 900]);
 *     $order->audit('order.repriced', ['reason' => $reason], withChanges: true);
 *     $order->auditTrail()->latest()->get();
 */
trait HasAuditTrail
{
    public function auditTrail(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'subject')->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  bool|list<string>  $withChanges  true = attach before/after of the last save; a list = only those attributes
     */
    public function audit(
        string $action,
        array $metadata = [],
        bool|array $withChanges = false,
        Model|false|null $actor = null,
    ): AuditLog {
        $auditor = app(Auditor::class);

        $changes = match (true) {
            $withChanges === true => $auditor->changesOf($this),
            is_array($withChanges) => $auditor->changesOf($this, $withChanges),
            default => [],
        };

        return $auditor->record($action, $this, $metadata, $changes, $actor);
    }
}
