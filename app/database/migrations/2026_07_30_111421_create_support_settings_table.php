<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business support desk and call-routing configuration (DATA-MODEL §5.9).
 *
 * The telephony defaults here are load-bearing rather than cosmetic:
 * call_routing_mode starts at 'tracking_only' because anything else touches the
 * business's real phone line, and ring_timeout_seconds must fire before the
 * carrier's own voicemail could answer (`29` §19.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();

            $table->jsonb('business_hours')->default('{}');
            $table->text('after_hours_message')->nullable();
            $table->jsonb('escalation_contacts')->default('[]');
            $table->jsonb('blocked_topics')->default('[]');

            $table->boolean('live_agent_enabled')->default(false);
            $table->jsonb('agent_schedule')->default('{}');
            $table->boolean('booking_enabled')->default(false);
            $table->integer('sla_minutes')->default(240);
            $table->integer('retention_days')->default(365);

            // tracking_only | conditional | proxy. A string cast to a backed
            // enum, never a database enum.
            $table->string('call_routing_mode')->default('tracking_only');
            $table->integer('ring_timeout_seconds')->default(18);

            $table->string('voicemail_greeting_type')->default('tts');
            $table->string('voicemail_media_id')->nullable();
            $table->string('after_hours_greeting_media_id')->nullable();
            $table->jsonb('on_call_numbers')->default('[]');

            // Escalate immediately, including overnight (`29` §19.6).
            $table->jsonb('emergency_keywords')
                ->default('["emergency","flood","leak","no heat","burst","gas"]');

            $table->timestamp('forwarding_verified_at')->nullable();
            $table->timestamp('forwarding_broken_at')->nullable();

            $table->timestamps();
        });

        DB::statement('ALTER TABLE support_settings ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE support_settings FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON support_settings
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('support_settings');
    }
};
