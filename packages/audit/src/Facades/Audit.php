<?php

namespace Mgamalhz\Audit\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Mgamalhz\Audit\Auditor;
use Mgamalhz\Audit\Models\AuditLog;

/**
 * @method static AuditLog record(string $action, ?Model $subject = null, array $metadata = [], array $changes = [], Model|false|null $actor = null)
 * @method static array changesOf(Model $model, ?array $only = null)
 *
 * @see Auditor
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Auditor::class;
    }
}
