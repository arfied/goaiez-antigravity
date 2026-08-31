<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class PurgeFixtureDataCommand extends Command
{
    protected $signature = 'db:purge-fixtures {--force : Actually delete matching fixture records}';

    protected $description = 'Idempotently purge synthetic fixture businesses and test @example.* users';

    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $userQuery = DB::table('users')->where(function ($query) {
            $query->where('email', 'LIKE', '%@example.com')
                ->orWhere('email', 'LIKE', '%@example.org')
                ->orWhere('email', 'LIKE', '%@example.net');
        });

        $businessQuery = DB::table('businesses')->where(function ($query) {
            $query->where('name', 'LIKE', 'Journey Verified Business%')
                ->orWhere('name', 'LIKE', 'Demo Enterprise%');
        });

        $userCount = $userQuery->count();
        $businessCount = $businessQuery->count();

        $this->info('Fixture Data Assessment:');
        $this->table(
            ['Entity', 'Filter Criteria', 'Matching Rows'],
            [
                ['Users', 'email LIKE %@example.(com|org|net)', (string) $userCount],
                ['Businesses', 'name LIKE "Journey Verified Business%" OR "Demo Enterprise%"', (string) $businessCount],
            ]
        );

        if (! $force) {
            $this->warn('DRY RUN: No rows were deleted. Pass --force to execute deletion.');

            return self::SUCCESS;
        }

        $this->warn('Executing deletion in transaction...');

        DB::transaction(function () use ($userQuery, $businessQuery) {
            $businessIds = (clone $businessQuery)->pluck('id')->all();
            if (! empty($businessIds)) {
                foreach (['work_orders', 'locations', 'subscriptions', 'opt_outs'] as $table) {
                    if (DB::getSchemaBuilder()->hasTable($table) && DB::getSchemaBuilder()->hasColumn($table, 'business_id')) {
                        DB::table($table)->whereIn('business_id', $businessIds)->delete();
                    }
                }
                $businessQuery->delete();
            }

            $userQuery->delete();
        });

        $this->info("Successfully purged {$businessCount} fixture business(es) and {$userCount} test user(s).");

        return self::SUCCESS;
    }
}
