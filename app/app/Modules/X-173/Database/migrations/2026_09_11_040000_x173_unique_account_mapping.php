<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The one writer of a ledger mapping is updateOrCreate() keyed on (business_id, connection_id,
 * internal_category), whose createOrFirst() catches a unique violation and returns the row that
 * won. Without a unique index there is nothing for that catch to catch, so two concurrent requests
 * for one category both insert and the mapping screen shows the category mapped twice. The index
 * is the whole fix: no writer changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_mappings')) {
            DB::statement(
                'CREATE UNIQUE INDEX account_mappings_business_connection_category_unique
                 ON account_mappings (business_id, connection_id, internal_category)'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS account_mappings_business_connection_category_unique');
    }
};
