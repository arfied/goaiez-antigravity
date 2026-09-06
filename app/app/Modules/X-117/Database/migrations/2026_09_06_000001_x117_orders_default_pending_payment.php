<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Row statuses and writers:
 * - pending_payment: CheckoutEngine::checkout() :83 and checkoutCart() :209
 * - paid: X-198's CaptureCheckedOutCart listener :28, on a non-null charge id
 * - cancelled: CheckoutEngine::cancelOrder() :129
 *
 * sold_out and refused are envelope-only and never reach the row.
 *
 * This file supersedes the `// paid, cancelled, sold_out` comment at
 * 2026_08_30_000029_create_x117_sellables_tables.php:46, which is left
 * byte-for-byte alone because that file is applied on every database in the programme.
 *
 * X-117, MONEY-57 (R245).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'pending_payment'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::statement("ALTER TABLE orders ALTER COLUMN status SET DEFAULT 'paid'");
    }
};
