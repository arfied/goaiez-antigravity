<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('conversations', 'csat_score')) {
            Schema::table('conversations', fn (Blueprint $t) => $t->dropColumn('csat_score'));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('conversations', 'csat_score')) {
            Schema::table('conversations', fn (Blueprint $t) => $t->smallInteger('csat_score')->nullable());
        }
    }
};
