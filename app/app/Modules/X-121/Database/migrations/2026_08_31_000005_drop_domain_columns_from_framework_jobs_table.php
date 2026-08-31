<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jobs')) {
            $domainColumns = ['business_id', 'quote_id', 'customer_id', 'title', 'total_amount', 'scheduled_at', 'completed_at'];
            $existingToDrop = array_filter($domainColumns, fn (string $col) => Schema::hasColumn('jobs', $col));

            if (! empty($existingToDrop)) {
                Schema::table('jobs', function (Blueprint $table) use ($existingToDrop) {
                    $table->dropColumn($existingToDrop);
                });
            }
        }
    }

    public function down(): void
    {
        // Framework jobs table retains queue structure
    }
};
