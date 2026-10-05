<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A turn the AI receptionist spoke on a phone call (AI receptionist plan, wave 3a, 2026-10-05).
 *
 * `call_id` names the call a turn belongs to; null is every turn written before this — text and chat turns, which belong to
 * a conversation instead. `metrics` holds the voice worker's per-turn timings (end of speech, speech-to-text, first token,
 * first audio) as it measured them. They are recorded, never asserted on (N-264F-01).
 *
 * ⚠️ One row per (call, turn number): the worker sends turns in batches and retries a batch it did not hear back about, so
 * the partial unique index is what makes a retried batch write nothing twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_turns', function (Blueprint $table): void {
            $table->foreignId('call_id')->nullable()->constrained('calls')->nullOnDelete();
            $table->jsonb('metrics')->nullable();
        });

        DB::statement('CREATE UNIQUE INDEX agent_turns_call_turn_unique ON agent_turns (call_id, turn_number) WHERE call_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agent_turns_call_turn_unique');

        Schema::table('agent_turns', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('call_id');
            $table->dropColumn('metrics');
        });
    }
};
