<?php

declare(strict_types=1);

use App\Enums\LiveAnswerMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who picks up first when a call reaches a business's number — owner ruling D-6, 2026-10-05 (AI receptionist plan).
 *
 * The owner's answer was "AI answers immediately, make it configurable": the AI receptionist picks up straight away
 * (`ai_first`, the default every business starts with) unless the owner chose to be rung first (`owner_first`).
 *
 * ⛔ `live_agent_enabled` IS DROPPED, NOT REUSED. It was a boolean defaulting to false with no reader and no writer anywhere
 * (`SupportSetting`'s docblock, 8552), so every value in it is a database default nobody chose, and a second per-business
 * switch beside this one would be two answers to one question. `voice.live_agent.enabled` stays the platform-wide switch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_settings', function (Blueprint $table): void {
            $table->dropColumn('live_agent_enabled');
            // A string cast to a backed enum, never a database enum (CLAUDE.md); the CHECK below is built from its cases.
            $table->string('live_answer_mode')->default(LiveAnswerMode::AiFirst->value);
        });

        $modes = collect(LiveAnswerMode::cases())
            ->map(fn (LiveAnswerMode $mode): string => "'{$mode->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE support_settings
                ADD CONSTRAINT support_settings_live_answer_mode_is_known
                CHECK (live_answer_mode IN ({$modes}))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE support_settings DROP CONSTRAINT IF EXISTS support_settings_live_answer_mode_is_known');

        Schema::table('support_settings', function (Blueprint $table): void {
            $table->dropColumn('live_answer_mode');
            $table->boolean('live_agent_enabled')->default(false);
        });
    }
};
