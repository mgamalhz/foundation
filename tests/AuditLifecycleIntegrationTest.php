<?php

use Illuminate\Support\Facades\DB;
use Mgamalhz\Audit\Testing\InteractsWithAudit;
use Mgamalhz\Foundation\Tests\Fixtures\Order;
use Mgamalhz\Foundation\Tests\Fixtures\OrderStatus;
use Mgamalhz\Foundation\Tests\Fixtures\User;

uses(InteractsWithAudit::class);

it('mirrors every transition into the audit log when both packages are installed', function () {
    $admin = User::create(['name' => 'Admin']);
    $this->actingAs($admin);
    $order = Order::create(['total' => 100]);

    $order->transitionTo(OrderStatus::Paid, reason: 'Card captured');

    $this->assertAudited($order, 'lifecycle.transitioned', by: $admin, metadata: [
        'attribute' => 'status',
        'from' => 'pending',
        'to' => 'paid',
        'reason' => 'Card captured',
    ]);
});

it('can be switched off', function () {
    config(['audit.record_transitions' => false]);
    $order = Order::create(['total' => 100]);

    $order->transitionTo(OrderStatus::Paid);

    $this->assertNotAudited($order, 'lifecycle.transitioned');
});

it('does not audit a transition that was rolled back', function () {
    $order = Order::create(['total' => 100]);

    try {
        DB::transaction(function () use ($order) {
            $order->transitionTo(OrderStatus::Paid);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    $this->assertNotAudited($order, 'lifecycle.transitioned');
});
