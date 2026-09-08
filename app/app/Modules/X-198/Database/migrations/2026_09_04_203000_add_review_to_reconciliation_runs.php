<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reconciliation_runs', function (Blueprint $table): void {
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reconciliation_runs', function (Blueprint $table): void {
            $table->dropColumn(['reviewed_at', 'reviewed_by_user_id']);
        });
    }
};
