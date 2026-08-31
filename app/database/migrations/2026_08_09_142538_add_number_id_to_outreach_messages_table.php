<?php

declare(strict_types=1);

use App\Services\Messaging\ReviewInviteSender;
use App\Services\Sms\PlatformTexter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which `phone_numbers` row sent this message — row 4 slice 6 phase 1.
 *
 * Nullable: every `outreach_messages` row written before this migration has no
 * number to name, and an owner alert or a billing notice is not an SMS at all.
 * {@see ReviewInviteSender} is the writer, carrying the
 * id {@see PlatformTexter} returns on {@see
 * \App\Services\Sms\SentText}.
 *
 * `restrictOnDelete()` rather than `cascadeOnDelete()`: `phone_numbers` rows are
 * never deleted by application code in this schema (retirement and release are
 * state transitions, not deletes), so this is a defensive choice rather than a
 * live path — a delete here would otherwise silently detach the historical
 * record of which number sent a real customer a real message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->foreignId('number_id')->nullable()
                ->after('channel')
                ->constrained('phone_numbers')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('number_id');
        });
    }
};
