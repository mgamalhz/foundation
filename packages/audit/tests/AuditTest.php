<?php

use Mgamalhz\Audit\Facades\Audit;
use Mgamalhz\Audit\Testing\InteractsWithAudit;
use Mgamalhz\Foundation\Tests\Fixtures\Order;
use Mgamalhz\Foundation\Tests\Fixtures\User;
use Mgamalhz\Support\Actor;
use PHPUnit\Framework\AssertionFailedError;

uses(InteractsWithAudit::class);

beforeEach(function () {
    $this->admin = User::create(['name' => 'Admin']);
    $this->order = Order::create(['status' => 'pending', 'total' => 500]);
});

it('records who did what to which record', function () {
    $this->actingAs($this->admin);

    $log = Audit::record('order.flagged', $this->order, ['reason' => 'suspicious']);

    expect($log->action)->toBe('order.flagged')
        ->and($log->actor->is($this->admin))->toBeTrue()
        ->and($log->subject->is($this->order))->toBeTrue()
        ->and($log->metadata)->toBe(['reason' => 'suspicious']);
});

it('records system actions with no actor', function () {
    $this->actingAs($this->admin);

    $log = Audit::record('order.expired', $this->order, actor: false);

    expect($log->actor_id)->toBeNull();
});

it('uses the actor set explicitly for jobs and commands', function () {
    $log = Actor::as($this->admin, fn () => Audit::record('order.exported', $this->order));

    expect($log->actor->is($this->admin))->toBeTrue()
        ->and(Actor::current())->toBeNull();
});

it('captures before and after of the last save', function () {
    $this->order->update(['total' => 900]);

    $log = $this->order->audit('order.repriced', ['reason' => 'discount'], withChanges: true);

    expect($log->changes)->toBe(['before' => ['total' => 500], 'after' => ['total' => 900]]);
});

it('redacts sensitive keys at any depth', function () {
    $log = Audit::record('staff.created', $this->admin, [
        'password' => 'secret123',
        'profile' => ['otp' => '4411', 'name' => 'Sara'],
    ]);

    expect($log->metadata)->toBe([
        'password' => '[redacted]',
        'profile' => ['otp' => '[redacted]', 'name' => 'Sara'],
    ]);
});

it('refuses actions that are not dot.notation', function (string $action) {
    Audit::record($action, $this->order);
})->with(['refund', 'Order.Refunded', 'order refunded'])->throws(InvalidArgumentException::class);

it('refuses unsaved subjects', function () {
    Audit::record('order.created', new Order(['status' => 'pending']));
})->throws(InvalidArgumentException::class, 'has not been saved yet');

it('keeps entries immutable', function () {
    $log = Audit::record('order.flagged', $this->order);

    expect(fn () => $log->update(['action' => 'order.cleared']))->toThrow(LogicException::class)
        ->and(fn () => $log->delete())->toThrow(LogicException::class);
});

it('exposes a per-record trail, newest first', function () {
    $this->order->audit('order.flagged');
    $this->order->audit('order.cleared');

    expect($this->order->auditTrail->pluck('action')->all())->toBe(['order.cleared', 'order.flagged']);
});

it('ships assertions that explain what was recorded instead', function () {
    $this->actingAs($this->admin);
    $this->order->audit('order.flagged', ['reason' => 'suspicious']);

    $this->assertAudited($this->order, 'order.flagged', by: $this->admin, metadata: ['reason' => 'suspicious']);
    $this->assertNotAudited($this->order, 'order.cleared');

    expect(fn () => $this->assertAudited($this->order, 'order.refunded'))
        ->toThrow(AssertionFailedError::class, 'Actions recorded in this test: order.flagged');
});
