<?php

namespace Mgamalhz\Foundation\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Mgamalhz\Lifecycle\Concerns\HasLifecycle;
use Mgamalhz\Lifecycle\StateMachine;

/** A plain-string state attribute (no enum cast), with a self-transition. */
class Ticket extends Model
{
    use HasLifecycle;

    protected $guarded = [];

    public static function stateMachine(): StateMachine
    {
        return StateMachine::for('state')
            ->initial('open')
            ->allow('open', ['assigned', 'closed'])
            ->allow('assigned', ['assigned', 'closed']);
    }
}
