<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * orders.order_number has no uniqueness, and two orders collide by birthday at roughly 38k orders.
 * This partial index scoped to (business_id, order_number) works with a bounded re-mint retry to fix it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX orders_business_order_number_unique ON orders (business_id, order_number)'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS orders_business_order_number_unique');
    }
};
