<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_links')) {
            DB::statement('ALTER TABLE payment_links ALTER COLUMN url TYPE text');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_links')) {
            DB::statement('ALTER TABLE payment_links ALTER COLUMN url TYPE varchar(255)');
        }
    }
};
