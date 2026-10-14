<?php

namespace Mgamalhz\Lifecycle\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/** The move is not declared in the model's stateMachine(). Usually a 422 for the caller. */
class InvalidTransition extends RuntimeException
{
    public ?string $from = null;

    public ?string $to = null;

    /** @param list<string> $allowed */
    public static function between(Model $model, string $attribute, ?string $from, string $to, array $allowed): self
    {
        $name = class_basename($model);
        $options = $allowed === [] ? 'none — it is a terminal state' : implode(', ', $allowed);

        $e = new self(
            "Cannot move {$name} #{$model->getKey()} {$attribute} from [{$from}] to [{$to}]. "
            ."Allowed from [{$from}]: {$options}. "
            ."If this is a real business rule, declare it in {$name}::stateMachine(); otherwise the caller is wrong."
        );
        $e->from = $from;
        $e->to = $to;

        return $e;
    }

    /** @param list<string> $states */
    public static function unknownState(Model $model, string $attribute, string $to, array $states): self
    {
        $name = class_basename($model);

        $e = new self("[{$to}] is not a {$name} {$attribute}. Known states: ".implode(', ', $states).'.');
        $e->to = $to;

        return $e;
    }
}
