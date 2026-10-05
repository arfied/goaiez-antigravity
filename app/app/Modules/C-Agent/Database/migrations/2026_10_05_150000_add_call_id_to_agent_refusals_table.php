<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A refusal the AI receptionist made on a phone call names that call (AI receptionist plan, wave 3b, 2026-10-05) — so the
 * owner can see that a caller asked for a price nobody had confirmed, and on which call. Null is every refusal written before
 * this, and every text or chat refusal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_refusals', function (Blueprint $table): void {
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_refusals', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('call_id');
        });
    }
};
