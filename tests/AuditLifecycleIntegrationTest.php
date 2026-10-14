<?php

use Illuminate\Support\Facades\DB;
use Mgamalhz\Audit\Testing\InteractsWithAudit;
use Mgamalhz\Foundation\Tests\Fixtures\Order;
use Mgamalhz\Foundation\Tests\Fixtures\OrderStatus;
use Mgamalhz\Foundation\Tests\Fixtures\User;

uses(InteractsWithAudit::class);

it('logs every transition to the activity log', function () {
    $admin = User::create(['name' => 'Admin']);
    $this->actingAs($admin);
    $order = Order::create(['total' => 100]);

    $order->transitionTo(OrderStatus::Paid, reason: 'Card captured');

    $this->assertActivityLogged($order, 'transitioned', by: $admin, properties: [
        'attribute' => 'status',
        'from' => 'pending',
        'to' => 'paid',
        'reason' => 'Card captured',
    ]);
});

it('logs system transitions without a causer', function () {
    $order = Order::create(['total' => 100]);

    $order->transitionTo(OrderStatus::Paid, actor: false);

    $this->assertActivityLogged($order, 'transitioned', by: false);
});

it('can be switched off', function () {
    config(['audit.record_transitions' => false]);
    $order = Order::create(['total' => 100]);

    $order->transitionTo(OrderStatus::Paid);

    $this->assertActivityNotLogged($order, 'transitioned');
});

it('does not log a transition that was rolled back', function () {
    $order = Order::create(['total' => 100]);

    try {
        DB::transaction(function () use ($order) {
            $order->transitionTo(OrderStatus::Paid);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    $this->assertActivityNotLogged($order, 'transitioned');
});
