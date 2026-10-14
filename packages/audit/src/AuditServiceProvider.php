<?php

namespace Mgamalhz\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Mgamalhz\Audit\Listeners\LogStateTransition;
use Mgamalhz\Support\Actor;
use Mgamalhz\Support\Value;
use Spatie\Activitylog\Support\CauserResolver;

/**
 * Adds to spatie/laravel-activitylog what it does not do by default:
 * redaction, immutability, Actor-aware causers and lifecycle logging.
 * Does nothing when activitylog is not installed.
 */
class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/audit.php', 'audit');
    }

    public function boot(): void
    {
        if (! class_exists(CauserResolver::class)) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/audit.php' => config_path('audit.php'),
            ], 'audit-config');
        }

        $this->resolveCausersThroughActor();
        $this->guardActivities();

        $event = 'Mgamalhz\\Lifecycle\\Events\\StateTransitioned';

        if (class_exists($event)) {
            Event::listen($event, LogStateTransition::class);
        }
    }

    /**
     * Make activitylog's causer follow Actor, so Actor::as($admin, ...) in jobs
     * and commands sets the causer exactly like it sets the lifecycle actor.
     */
    private function resolveCausersThroughActor(): void
    {
        $this->app->make(CauserResolver::class)->resolveUsing(function (Model|int|string|null $subject) {
            if ($subject instanceof Model) {
                return $subject;
            }

            if ($subject === null) {
                return Actor::current();
            }

            $provider = auth()->guard(config('activitylog.default_auth_driver'))->getProvider();

            return $provider->retrieveById($subject);
        });
    }

    private function redact(Model $activity): void
    {
        foreach (['properties', 'attribute_changes'] as $attribute) {
            $value = $activity->getAttribute($attribute);

            if ($value !== null) {
                $activity->setAttribute($attribute, Value::redact(collect($value)->toArray(), config('audit.redact', [])));
            }
        }
    }

    private function guardActivities(): void
    {
        /** @var class-string<Model> $model */
        $model = config('activitylog.activity_model');

        $model::saving(fn (Model $activity) => $this->redact($activity));

        $model::updating(function () {
            if (config('audit.immutable', true)) {
                throw new LogicException('Activity log entries are immutable and cannot be updated.');
            }
        });

        $model::deleting(function () {
            if (config('audit.immutable', true)) {
                throw new LogicException('Activity log entries are immutable. Old entries are removed with `php artisan activitylog:clean`.');
            }
        });
    }
}
