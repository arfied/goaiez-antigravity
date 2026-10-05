<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The message a caller left with the AI receptionist (AI receptionist plan, wave 3c, 2026-10-05) — one per call, written by
 * `VoiceCalls::leaveLiveMessage()`, read on the owner's calls page, and announced to the owner by email (which carries none
 * of it). Every column is null for a call that left no message, which is every call before this wave.
 *
 * ⛔ No consent is recorded with it: a caller asking to be rung back has asked for a call back, nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->string('message_name', 120)->nullable();
            $table->string('message_callback', 32)->nullable();
            $table->text('message_text')->nullable();
            $table->timestamp('message_left_at')->nullable();
            $table->timestamp('message_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table): void {
            $table->dropColumn(['message_name', 'message_callback', 'message_text', 'message_left_at', 'message_notified_at']);
        });
    }
};
