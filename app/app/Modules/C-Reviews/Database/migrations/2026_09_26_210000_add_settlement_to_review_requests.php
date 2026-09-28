<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('review_requests', 'settled_reason')) {
            Schema::table('review_requests', function (Blueprint $table) {
                $table->string('settled_reason', 120)->nullable();
                $table->timestamp('settled_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('review_requests', 'settled_reason')) {
            Schema::table('review_requests', function (Blueprint $table) {
                $table->dropColumn('settled_reason');
                $table->dropColumn('settled_at');
            });
        }
    }
};
