<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one writer of a merchant connection is updateOrCreate() keyed on (business_id, gateway_name),
 * whose createOrFirst() catches a unique violation and returns the row that won. Without a unique
 * index there is nothing for that catch to catch, so two concurrent connects for one gateway both
 * insert and the business carries two connections for one gateway. The index is the whole fix: no
 * writer changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('merchant_connections')) {
            DB::statement(
                'CREATE UNIQUE INDEX merchant_connections_business_gateway_unique
                 ON merchant_connections (business_id, gateway_name)'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS merchant_connections_business_gateway_unique');
    }
};
