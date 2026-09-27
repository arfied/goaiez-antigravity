<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * a, b or c — which of the three derived looks of its industry's starting point this business picked on Build my site; null = a; a choice, not a frozen design (a later industry change re-derives the same letter from the new family).
     */
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $t) {
            $t->string('site_variant', 1)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $t) {
            $t->dropColumn('site_variant');
        });
    }
};
