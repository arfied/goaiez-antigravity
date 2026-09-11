<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('qa_tickets', 'csat_score')) {
            Schema::table('qa_tickets', fn (Blueprint $t) => $t->dropColumn('csat_score'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('qa_tickets', 'csat_score')) {
            Schema::table('qa_tickets', fn (Blueprint $t) => $t->integer('csat_score')->nullable());
        }
    }
};
