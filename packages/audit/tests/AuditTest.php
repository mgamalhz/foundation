<?php

use Mgamalhz\Audit\Testing\InteractsWithAudit;
use Mgamalhz\Foundation\Tests\Fixtures\Order;
use Mgamalhz\Foundation\Tests\Fixtures\User;
use Mgamalhz\Support\Actor;
use PHPUnit\Framework\AssertionFailedError;
use Spatie\Activitylog\Models\Activity;

uses(InteractsWithAudit::class);

beforeEach(function () {
    $this->admin = User::create(['name' => 'Admin']);
    $this->order = Order::create(['total' => 500]);
});

it('uses the authenticated user as the causer', function () {
    $this->actingAs($this->admin);

    activity()->performedOn($this->order)->event('flagged')->log('Order flagged');

    $this->assertActivityLogged($this->order, 'flagged', by: $this->admin);
});

it('uses the Actor set for jobs and commands as the causer', function () {
    Actor::as($this->admin, fn () => activity()->performedOn($this->order)->event('exported')->log('Exported'));

    $this->assertActivityLogged($this->order, 'exported', by: $this->admin);
});

it('logs nothing as the causer when the system acts', function () {
    $this->actingAs($this->admin);

    Actor::asSystem(fn () => activity()->performedOn($this->order)->event('expired')->log('Expired'));

    $this->assertActivityLogged($this->order, 'expired', by: false);
});

it('still honours an explicit causedBy()', function () {
    $other = User::create(['name' => 'Other']);
    $this->actingAs($this->admin);

    activity()->performedOn($this->order)->causedBy($other)->event('approved')->log('Approved');

    $this->assertActivityLogged($this->order, 'approved', by: $other);
});

it('redacts sensitive keys at any depth before saving', function () {
    activity()->performedOn($this->order)->event('staff_created')->withProperties([
        'password' => 'secret123',
        'profile' => ['otp' => '4411', 'name' => 'Sara'],
    ])->log('Staff created');

    expect(Activity::query()->latest('id')->first()->properties->toArray())->toBe([
        'password' => '[redacted]',
        'profile' => ['otp' => '[redacted]', 'name' => 'Sara'],
    ]);
});

it('keeps activities immutable', function () {
    $activity = activity()->performedOn($this->order)->event('flagged')->log('Flagged');

    expect(fn () => $activity->update(['description' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => $activity->delete())->toThrow(LogicException::class, 'activitylog:clean');
});

it('ships assertions that say what was logged instead', function () {
    activity()->performedOn($this->order)->event('flagged')->withProperties(['reason' => 'suspicious'])->log('Flagged');

    $this->assertActivityLogged($this->order, 'flagged', properties: ['reason' => 'suspicious']);
    $this->assertActivityNotLogged($this->order, 'refunded');

    expect(fn () => $this->assertActivityLogged($this->order, 'refunded'))
        ->toThrow(AssertionFailedError::class, 'Events logged for this subject: flagged');
});
