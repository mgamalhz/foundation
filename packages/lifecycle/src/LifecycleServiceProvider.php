<?php

namespace Mgamalhz\Lifecycle;

use Illuminate\Support\ServiceProvider;
use Mgamalhz\Lifecycle\Commands\ShowLifecycleCommand;

class LifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/lifecycle.php', 'lifecycle');

        $this->app->singleton(Transitioner::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([ShowLifecycleCommand::class]);

            $this->publishes([
                __DIR__.'/../config/lifecycle.php' => config_path('lifecycle.php'),
            ], 'lifecycle-config');

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'lifecycle-migrations');
        }
    }
}
