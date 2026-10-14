<?php

namespace Mgamalhz\Lifecycle\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class InvalidInitialState extends LogicException
{
    /** @param list<string> $initial */
    public static function make(Model $model, string $attribute, ?string $state, array $initial): self
    {
        $name = class_basename($model);

        return new self(
            "A new {$name} must start with {$attribute} in [".implode(', ', $initial)."], got [{$state}]. "
            .'Create it in an initial state and then call transitionTo(), or use '
            ."{$name}::withoutLifecycleGuard(fn () => ...) in seeders and factories."
        );
    }
}
