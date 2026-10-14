<?php

namespace Mgamalhz\Foundation\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mgamalhz\Audit\AuditServiceProvider;
use Mgamalhz\Lifecycle\LifecycleServiceProvider;
use Mgamalhz\Support\Actor;
use Mgamalhz\Support\SupportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SupportServiceProvider::class,
            AuditServiceProvider::class,
            LifecycleServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', Fixtures\User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        // The packages' own migrations (audit_logs, state_transitions).
        $this->artisan('migrate', ['--database' => 'testing'])->run();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('cancel_reason')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('state');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Actor::flush();

        parent::tearDown();
    }
}
