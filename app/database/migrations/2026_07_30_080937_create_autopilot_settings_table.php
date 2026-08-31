<?php

declare(strict_types=1);

use App\Enums\AutomationMode;
use App\Enums\BrandVoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-location autopilot configuration (DATA-MODEL §5.4). One row per
 * location; defaults are ungated (`29` §2 rule 37).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autopilot_settings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('automation_mode')->default(AutomationMode::Auto->value);

            $table->jsonb('hold_window_overrides')->default('{}');

            // TEXT[] in DATA-MODEL; jsonb list here (no native array type).
            // CONFIRM is reserved for exactly these three, and they are not
            // the owner's to remove (`29` §2 rule 37).
            $table->jsonb('require_confirm_for')
                ->default('["gbp_core_fields","spend","new_campaign_type"]');

            // 5 by owner override of 2026-07-30 (decision 110, superseding the
            // 0 in decision 33 — both positions are written up in DECISIONS.md
            // §Review invitation; do not re-open). Per-destination thresholds
            // live in review_destinations (111). ⚠️ This column was dropped at
            // 304 and the "no threshold without a logged gating_ack_at" rule it
            // cited was removed by the owner at 2074.
            $table->smallInteger('google_invite_threshold')->default(5);

            $table->smallInteger('triage_threshold')->default(3);
            $table->smallInteger('reply_auto_post_min')->default(5);

            $table->boolean('auto_approve_5_star')->default(true);
            $table->boolean('auto_reply')->default(true);
            $table->boolean('full_auto_post_replies')->default(true);
            $table->boolean('send_review_requests')->default(true);
            $table->boolean('post_to_google_profile')->default(true);
            $table->boolean('update_review_hub')->default(true);
            $table->boolean('post_to_facebook')->default(false);
            $table->boolean('recycle_reviews_to_social')->default(false);
            $table->boolean('support_bot_enabled')->default(true);

            $table->jsonb('outreach_channels')->default('["sms","email"]');

            $table->string('brand_voice')->default(BrandVoice::FriendlyWarm->value);
            $table->text('brand_voice_examples')->nullable();

            // ⚠️ `gating_ack_at` WAS DECLARED HERE AND IS DROPPED BY A LATER
            // MIGRATION (2074, 2660). Left out of this one rather than removed
            // from history: `down()` on the drop restores it, so a rollback past
            // this point has to find the column where it was. See
            // `2026_08_12_004038_drop_gating_ack_at_from_autopilot_settings_table`
            // for why the acknowledgement went and what replaced its test.
            $table->timestamp('gating_ack_at')->nullable();

            $table->timestamps();

            $table->index('business_id');
        });

        DB::statement('ALTER TABLE autopilot_settings ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE autopilot_settings FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON autopilot_settings
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('autopilot_settings');
    }
};
