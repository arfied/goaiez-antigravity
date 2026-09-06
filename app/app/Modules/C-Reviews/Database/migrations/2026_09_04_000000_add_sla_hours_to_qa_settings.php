<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qa_settings', function (Blueprint $table): void {
            $table->unsignedInteger('sla_hours')->default(48);
        });
    }

    public function down(): void
    {
        Schema::table('qa_settings', function (Blueprint $table): void {
            $table->dropColumn('sla_hours');
        });
    }
};
