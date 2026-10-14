<?php

namespace Mgamalhz\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * Answers one question for every foundation package: "who is doing this?".
 *
 * By default the actor is whoever is authenticated on the guards listed in
 * `foundation.actor_guards` (the default guard when that list is empty). Queued
 * jobs, commands and webhooks have no authenticated user, so they say who they
 * act for explicitly:
 *
 *     Actor::as($admin, fn () => $order->transitionTo(OrderStatus::Refunded));
 *     Actor::asSystem(fn () => $subscription->transitionTo(Status::Expired));
 *
 * Package APIs accept an `$actor` argument with the same three meanings:
 * `null` = resolve the current actor, a Model = exactly that actor,
 * `false` = deliberately no actor (the system did it).
 */
final class Actor
{
    /** Stack of explicit overrides; `false` on the stack means "the system". */
    private static array $stack = [];

    private static ?Closure $resolver = null;

    /**
     * Run `$callback` with `$actor` as the current actor, restoring the previous
     * actor afterwards even if the callback throws.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function as(?Model $actor, Closure $callback): mixed
    {
        self::$stack[] = $actor ?? false;

        try {
            return $callback();
        } finally {
            array_pop(self::$stack);
        }
    }

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function asSystem(Closure $callback): mixed
    {
        return self::as(null, $callback);
    }

    /** Replace the default (auth-based) resolution, e.g. for a multi-guard app. */
    public static function resolveUsing(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    public static function current(): ?Model
    {
        if (self::$stack !== []) {
            $top = end(self::$stack);

            return $top === false ? null : $top;
        }

        if (self::$resolver !== null) {
            return (self::$resolver)();
        }

        $guards = config('foundation.actor_guards') ?: [null];

        foreach ($guards as $guard) {
            $user = auth()->guard($guard)->user();

            if ($user instanceof Model) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Normalise the `$actor` argument used across the packages.
     *
     * @param  Model|false|null  $actor  null = current, false = system, Model = explicit
     */
    public static function resolve(Model|false|null $actor): ?Model
    {
        return match (true) {
            $actor === false => null,
            $actor instanceof Model => $actor,
            default => self::current(),
        };
    }

    /** @internal Reset state between tests. */
    public static function flush(): void
    {
        self::$stack = [];
        self::$resolver = null;
    }
}
