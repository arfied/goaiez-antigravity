<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * null = the agreement carries no late-fee term and a fee is refused until the owner writes one (G1-71);
     * the cap is a ROW, never the engine's 10 % / $50 literal (P-193).
     */
    public function up(): void
    {
        Schema::table('ar_plan_terms', function (Blueprint $table) {
            $table->unsignedSmallInteger('late_fee_percent')->nullable()->after('max_term_days');
            $table->unsignedInteger('late_fee_cap_cents')->nullable()->after('late_fee_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ar_plan_terms', function (Blueprint $table) {
            $table->dropColumn(['late_fee_percent', 'late_fee_cap_cents']);
        });
    }
};
