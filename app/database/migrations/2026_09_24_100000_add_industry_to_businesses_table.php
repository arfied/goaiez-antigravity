<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * one of IndustryFamily's six values, derived from the Google Places categories at provisioning or back-filled by the competitor sweep; null = Places gave nothing recognisable; the owner's own answer lives on the facts sheet and wins over this column (see IndustryResolver); NOT the benchmark vertical.
     */
    public function up(): void
    {
        Schema::table('businesses', fn (Blueprint $t) => $t->string('industry', 16)->nullable()->index());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', fn (Blueprint $t) => $t->dropColumn('industry'));
    }
};
