<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('review_requests', 'customer_name')) {
            Schema::table('review_requests', function (Blueprint $table) {
                $table->string('customer_name')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('review_requests', 'customer_name')) {
            Schema::table('review_requests', function (Blueprint $table) {
                $table->dropColumn('customer_name');
            });
        }
    }
};
