<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mgamalhz\Foundation\Tests\Fixtures\Order;
use Mgamalhz\Foundation\Tests\Fixtures\OrderStatus;
use Mgamalhz\Foundation\Tests\Fixtures\Ticket;
use Mgamalhz\Foundation\Tests\Fixtures\User;
use Mgamalhz\Lifecycle\Events\StateTransitioned;
use Mgamalhz\Lifecycle\Exceptions\DirectStateMutation;
use Mgamalhz\Lifecycle\Exceptions\InvalidInitialState;
use Mgamalhz\Lifecycle\Exceptions\InvalidTransition;
use Mgamalhz\Lifecycle\Exceptions\TransitionDenied;
use Mgamalhz\Lifecycle\Testing\InteractsWithLifecycle;
use Mgamalhz\Support\Actor;
use PHPUnit\Framework\AssertionFailedError;

uses(InteractsWithLifecycle::class);

beforeEach(function () {
    $this->admin = User::create(['name' => 'Admin']);
});

describe('creating', function () {
    it('starts in the initial state when none is given', function () {
        $order = Order::create(['total' => 100]);

        expect($order->status)->toBe(OrderStatus::Pending);
        $this->assertTransitioned($order, from: null, to: OrderStatus::Pending);
    });

    it('refuses to create a record in a non-initial state', function () {
        Order::create(['status' => OrderStatus::Shipped]);
    })->throws(InvalidInitialState::class, 'must start with status in [pending]');

    it('lets seeders bypass the guard explicitly', function () {
        $order = Order::withoutLifecycleGuard(fn () => Order::create(['status' => OrderStatus::Shipped]));

        expect($order->status)->toBe(OrderStatus::Shipped);
    });
});

describe('transitioning', function () {
    it('moves along a declared transition and records who did it and why', function () {
        $this->actingAs($this->admin);
        $order = Order::create(['total' => 100]);

        $order->transitionTo(OrderStatus::Paid, reason: 'Card captured', metadata: ['gateway' => 'moyasar']);

        expect($order->status)->toBe(OrderStatus::Paid)
            ->and($order->fresh()->status)->toBe(OrderStatus::Paid);

        $record = $this->assertTransitioned($order, from: OrderStatus::Pending, to: OrderStatus::Paid, by: $this->admin);
        expect($record->reason)->toBe('Card captured')
            ->and($record->metadata)->toBe(['gateway' => 'moyasar']);
    });

    it('works with plain string states and declared self-transitions', function () {
        $ticket = Ticket::create([]);

        $ticket->transitionTo('assigned');
        $ticket->transitionTo('assigned'); // reassignment

        expect($ticket->stateTransitions->pluck('to')->all())->toBe(['open', 'assigned', 'assigned']);
    });

    it('rejects undeclared moves with a message that lists what is allowed', function () {
        $order = Order::create(['total' => 100]);

        $order->transitionTo(OrderStatus::Delivered);
    })->throws(InvalidTransition::class, 'Allowed from [pending]: paid, cancelled');

    it('says when a state is terminal', function () {
        $order = Order::create(['total' => 100]);
        $order->transitionTo(OrderStatus::Cancelled);

        $order->transitionTo(OrderStatus::Paid);
    })->throws(InvalidTransition::class, 'none — it is a terminal state');

    it('rejects states that do not exist', function () {
        Order::create(['total' => 100])->transitionTo('teleported');
    })->throws(InvalidTransition::class, 'Known states:');

    it('runs guards and surfaces their reason', function () {
        $order = Order::create(['total' => 0]);
        $order->transitionTo(OrderStatus::Paid);

        $order->transitionTo(OrderStatus::Refunded);
    })->throws(TransitionDenied::class, 'Nothing was charged');

    it('applies extra changes in the same write', function () {
        $order = Order::create(['total' => 100]);

        $order->transitionTo(OrderStatus::Cancelled, mutate: fn (Order $o) => $o->cancel_reason = 'customer');

        expect($order->fresh()->cancel_reason)->toBe('customer')
            ->and($order->cancel_reason)->toBe('customer');
    });

    it('refuses to silently drop unsaved changes on the instance', function () {
        $order = Order::create(['total' => 100]);
        $order->cancel_reason = 'customer';

        $order->transitionTo(OrderStatus::Cancelled);
    })->throws(LogicException::class, 'unsaved changes [cancel_reason]');

    it('validates against the stored state, not the caller\'s stale copy', function () {
        $order = Order::create(['total' => 100]);
        $stale = Order::find($order->id);

        $order->transitionTo(OrderStatus::Cancelled); // e.g. the customer cancels first

        expect(fn () => $stale->transitionTo(OrderStatus::Paid)) // a late payment callback
            ->toThrow(InvalidTransition::class, 'from [cancelled] to [paid]');
        expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    });

    it('rolls everything back when the surrounding transaction fails', function () {
        $order = Order::create(['total' => 100]);

        try {
            DB::transaction(function () use ($order) {
                $order->transitionTo(OrderStatus::Paid);
                throw new RuntimeException('payment provider timeout');
            });
        } catch (RuntimeException) {
        }

        expect($order->fresh()->status)->toBe(OrderStatus::Pending)
            ->and($order->fresh()->stateTransitions)->toHaveCount(1);
    });

    it('records the actor set for jobs, or none for the system', function () {
        $order = Order::create(['total' => 100]);

        Actor::as($this->admin, fn () => $order->transitionTo(OrderStatus::Paid));
        $order->transitionTo(OrderStatus::Shipped, actor: false);

        $this->assertTransitioned($order, OrderStatus::Pending, OrderStatus::Paid, by: $this->admin);
        expect($order->stateTransitions->last()->actor_id)->toBeNull();
    });
});

describe('protection', function () {
    it('refuses direct assignment of the state attribute', function () {
        $order = Order::create(['total' => 100]);
        $order->status = OrderStatus::Shipped;
        $order->save();
    })->throws(DirectStateMutation::class, '$order->transitionTo(...)');

    it('still allows saving other attributes', function () {
        $order = Order::create(['total' => 100]);
        $order->update(['total' => 250]);

        expect($order->fresh()->total)->toBe(250);
    });
});

describe('querying', function () {
    it('offers only the moves that are declared and pass guards', function () {
        $paid = Order::create(['total' => 100]);
        $paid->transitionTo(OrderStatus::Paid);

        $free = Order::create(['total' => 0]);
        $free->transitionTo(OrderStatus::Paid);

        expect($paid->allowedTransitions())->toBe([OrderStatus::Shipped, OrderStatus::Refunded])
            ->and($free->allowedTransitions())->toBe([OrderStatus::Shipped])
            ->and($free->canTransitionTo(OrderStatus::Refunded))->toBeFalse();
    });

    it('knows terminal states', function () {
        $order = Order::create(['total' => 100]);
        $order->transitionTo(OrderStatus::Cancelled);

        expect($order->isInTerminalState())->toBeTrue();
    });
});

describe('events', function () {
    it('fires StateTransitioned only after the transaction commits', function () {
        Event::fake([StateTransitioned::class]);
        $order = Order::create(['total' => 100]);

        DB::transaction(function () use ($order) {
            $order->transitionTo(OrderStatus::Paid);
            Event::assertNotDispatched(StateTransitioned::class);
        });

        Event::assertDispatched(StateTransitioned::class, fn (StateTransitioned $e) => $e->is(Order::class, OrderStatus::Paid) && $e->from === 'pending');
    });
});

describe('introspection', function () {
    it('exposes the declared rules for tests', function () {
        $this->assertCanTransition(Order::class, OrderStatus::Paid, OrderStatus::Refunded);
        $this->assertCannotTransition(Order::class, OrderStatus::Delivered, OrderStatus::Cancelled);
    });

    it('prints the lifecycle for humans and agents', function () {
        $this->artisan('lifecycle:show', ['model' => Order::class])
            ->expectsOutputToContain('attribute: status')
            ->assertSuccessful();

        $this->artisan('lifecycle:show', ['model' => Order::class, '--mermaid' => true])
            ->expectsOutputToContain('paid --> refunded : guarded')
            ->assertSuccessful();
    });

    it('explains a failed assertion with the real timeline', function () {
        $order = Order::create(['total' => 100]);

        expect(fn () => $this->assertTransitioned($order, OrderStatus::Paid, OrderStatus::Shipped))
            ->toThrow(AssertionFailedError::class, 'Actual timeline: ∅ → pending');
    });
});
