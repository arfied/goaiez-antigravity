<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An authorisation pays once. CheckoutEngine::checkoutCart() refuses a reused one with a read before its
 * insert, which refuses a second press but not two concurrent ones: both read nothing and both place an
 * order. This index makes the second insert fail; the checkout's retry loop then re-runs the guard,
 * which refuses exactly as it refuses a sequential second press.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX orders_business_auth_token_unique ON orders (business_id, auth_token)'
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS orders_business_auth_token_unique');
    }
};
