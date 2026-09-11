<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Both writers of a cart are updateOrCreate() keyed on (business_id, session_token), whose
 * createOrFirst() catches a unique violation and returns the row that won. Without a unique
 * index there is nothing for that catch to catch, so two concurrent requests for one session
 * both insert and every reader's first() picks one of the two. The index is the whole fix:
 * no writer changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('carts')) {
            DB::statement(
                'CREATE UNIQUE INDEX carts_business_session_unique
                 ON carts (business_id, session_token)'
            );
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS carts_business_session_unique');
    }
};
