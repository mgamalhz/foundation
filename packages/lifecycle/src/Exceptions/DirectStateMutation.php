<?php

namespace Mgamalhz\Lifecycle\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Someone assigned the state attribute and called save(). Always a bug in the caller. */
class DirectStateMutation extends LogicException
{
    public static function make(Model $model, string $attribute): self
    {
        $name = class_basename($model);
        $variable = '$'.lcfirst($name);

        return new self(
            "{$name}::\${$attribute} is managed by its lifecycle and cannot be assigned directly. "
            ."Use {$variable}->transitionTo(...) so the change is validated, locked and recorded. "
            ."Seeders and one-off data fixes may wrap the write in {$name}::withoutLifecycleGuard(fn () => ...)."
        );
    }
}
