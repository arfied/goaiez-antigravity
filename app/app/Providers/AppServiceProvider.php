<?php

namespace App\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Schema commands, which need DDL and therefore the owner role.
     *
     * @var list<string>
     */
    private const SCHEMA_COMMANDS = [
        'migrate',
        'migrate:fresh',
        'migrate:install',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'migrate:status',
        'db:wipe',
        'schema:dump',
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->routeSchemaCommandsToTheOwnerRole();
        $this->forbidLiveVendorCallsInTests();
        $this->wireWorkerHeartbeat();
    }

    private function wireWorkerHeartbeat(): void
    {
        Queue::looping(function (): void {
            cache()->put('goaiez:worker:heartbeat', now(), 300);
        });
    }

    private function forbidLiveVendorCallsInTests(): void
    {
        if ($this->app->runningUnitTests()) {
            Http::preventStrayRequests();
        }
    }

    private function routeSchemaCommandsToTheOwnerRole(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        /** @var list<string> $argv */
        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? null;

        if (! in_array($command, self::SCHEMA_COMMANDS, true)) {
            return;
        }

        foreach ($argv as $argument) {
            if ($argument === '--database' || str_starts_with($argument, '--database=')) {
                return;
            }
        }

        config(['database.default' => 'pgsql_migrate']);
    }
}
