<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            $table->string('cta_type', 20)->nullable();
            $table->string('cta_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gbp_posts', function (Blueprint $table) {
            $table->dropColumn(['cta_type', 'cta_url']);
        });
    }
};
