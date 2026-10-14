<?php

namespace Mgamalhz\Support;

use Illuminate\Support\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/foundation.php', 'foundation');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/foundation.php' => config_path('foundation.php'),
            ], 'foundation-config');
        }
    }
}
