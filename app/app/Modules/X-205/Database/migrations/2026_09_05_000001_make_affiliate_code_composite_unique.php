<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * (R245) Made affiliate_code unique composite with business_id to avoid cross-tenant collisions.
     */
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropUnique('affiliates_affiliate_code_unique');
            $table->unique(['business_id', 'affiliate_code'], 'affiliates_business_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropUnique('affiliates_business_code_unique');
            $table->unique('affiliate_code', 'affiliates_affiliate_code_unique');
        });
    }
};
