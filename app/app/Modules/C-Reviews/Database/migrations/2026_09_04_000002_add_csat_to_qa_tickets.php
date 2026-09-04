<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('qa_tickets', 'csat_requested_at')) {
            Schema::table('qa_tickets', function (Blueprint $table) {
                $table->timestamp('csat_requested_at')->nullable();
                $table->integer('csat_score')->nullable();
                $table->timestamp('reopened_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('qa_tickets', function (Blueprint $table) {
            $table->dropColumn(['csat_requested_at', 'csat_score', 'reopened_at']);
        });
    }
};
