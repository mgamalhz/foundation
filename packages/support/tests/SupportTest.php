<?php

use Mgamalhz\Foundation\Tests\Fixtures\OrderStatus;
use Mgamalhz\Foundation\Tests\Fixtures\User;
use Mgamalhz\Support\Actor;
use Mgamalhz\Support\Value;

it('resolves the authenticated user as the actor', function () {
    $user = User::create(['name' => 'Sara']);
    $this->actingAs($user);

    expect(Actor::current()->is($user))->toBeTrue();
});

it('restores the previous actor even when the callback throws', function () {
    $user = User::create(['name' => 'Sara']);

    expect(fn () => Actor::as($user, fn () => throw new RuntimeException))->toThrow(RuntimeException::class)
        ->and(Actor::current())->toBeNull();
});

it('lets the system act with no actor while a user is logged in', function () {
    $this->actingAs(User::create(['name' => 'Sara']));

    expect(Actor::asSystem(fn () => Actor::current()))->toBeNull();
});

it('normalises enums and scalars to stored keys', function () {
    expect(Value::key(OrderStatus::Paid))->toBe('paid')
        ->and(Value::key('open'))->toBe('open')
        ->and(Value::key(3))->toBe('3')
        ->and(Value::key(null))->toBeNull();
});
