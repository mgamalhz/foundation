<?php

namespace Mgamalhz\Audit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Mgamalhz\Audit\Listeners\RecordStateTransition;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/audit.php', 'audit');

        $this->app->singleton(Auditor::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/audit.php' => config_path('audit.php'),
            ], 'audit-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'audit-migrations');
        }

        // Soft integration: only when mgamalhz/lifecycle is installed too.
        $event = 'Mgamalhz\\Lifecycle\\Events\\StateTransitioned';

        if (class_exists($event)) {
            Event::listen($event, RecordStateTransition::class);
        }
    }
}
