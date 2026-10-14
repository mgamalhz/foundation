<?php

namespace Mgamalhz\Lifecycle\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** The move is declared, but a guard said no. `$reason` is safe to show to the user. */
class TransitionDenied extends RuntimeException
{
    public ?string $reason = null;

    public static function make(Model $model, string $attribute, string $to, ?string $reason): self
    {
        $name = class_basename($model);

        $e = new self("{$name} #{$model->getKey()} cannot move {$attribute} to [{$to}]".($reason ? ": {$reason}" : ' (a guard on this transition returned false).'));
        $e->reason = $reason;

        return $e;
    }
}
