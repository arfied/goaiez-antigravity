<?php

declare(strict_types=1);

use App\Enums\CallAnsweredBy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who answered a call this platform picked up live — the AI receptionist plan, wave 2 (2026-10-05).
 *
 * ⚠️ NULL IS THE ORDINARY VALUE FOR EVERY CALL RECORDED FROM THE CARRIER'S WEBHOOK, which is every call before this wave:
 * those calls were answered by the owner's own phone or by nobody, and this platform did not pick them up. A value is
 * written only by `VoiceCalls::answerLive()`, at the moment the AI receptionist answers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            // Cast to CallAnsweredBy. A string, never a database enum (CLAUDE.md).
            $table->string('answered_by')->nullable();
        });

        $values = collect(CallAnsweredBy::cases())
            ->map(fn (CallAnsweredBy $by): string => "'{$by->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE calls
                ADD CONSTRAINT calls_answered_by_is_known
                CHECK (answered_by IS NULL OR answered_by IN ({$values}))
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE calls DROP CONSTRAINT IF EXISTS calls_answered_by_is_known');

        Schema::table('calls', function (Blueprint $table): void {
            $table->dropColumn('answered_by');
        });
    }
};
