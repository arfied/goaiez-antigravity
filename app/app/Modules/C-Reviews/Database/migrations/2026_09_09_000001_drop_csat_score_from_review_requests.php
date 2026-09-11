<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('review_requests', 'csat_score')) {
            Schema::table('review_requests', fn (Blueprint $t) => $t->dropColumn('csat_score'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('review_requests', 'csat_score')) {
            Schema::table('review_requests', fn (Blueprint $t) => $t->unsignedTinyInteger('csat_score')->nullable());
        }
    }
};
