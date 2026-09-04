<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('review_requests', function (Blueprint $table): void {
            $table->unsignedTinyInteger('csat_score')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('review_requests', function (Blueprint $table): void {
            $table->dropColumn('csat_score');
        });
    }
};
