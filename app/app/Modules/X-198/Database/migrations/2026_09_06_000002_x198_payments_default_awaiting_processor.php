<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Row statuses and writers:
 * - awaiting_processor: GatewayEngine::capture() when no adapter was asked
 * - captured: GatewayEngine::capture() on a non-null charge id
 * - failed: GatewayEngine::capture() catch block
 *
 * This file supersedes the `// captured, failed, refunded, chargeback` comment at
 * 2026_08_30_000030_create_x198_gateway_tables.php:35, which is left
 * byte-for-byte alone because that file is applied on every database in the programme.
 *
 * X-198, MONEY-61 (R245).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::statement("ALTER TABLE payments ALTER COLUMN status SET DEFAULT 'awaiting_processor'");
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        DB::statement("ALTER TABLE payments ALTER COLUMN status SET DEFAULT 'captured'");
    }
};
