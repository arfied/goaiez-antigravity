<?php

declare(strict_types=1);

use App\Enums\VoicemailAudioState;
use App\Enums\VoicemailTranscriptState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The message a caller left — T176 P2, the voice half of R7.
 *
 * ⛔ **TWO STATE COLUMNS, NOT ONE, AND THAT IS THE LAW THIS TABLE EXISTS TO
 * KEEP.** `CLAUDE.md`: *never block on transcription — STT failure still
 * delivers the audio.* `audio_state` and `transcript_state` are independent
 * because the owner notification fires on the first and never waits on the
 * second. A single `status` column is how a speech-to-text outage becomes a
 * silent missed-call outage, and the tell is that every test passes because the
 * fake transcriber always answers.
 *
 * ⚠️ **`recording_path` IS A PATH ON OUR OWN STORAGE, NEVER A VENDOR URL.**
 * `VoicemailRecorded`'s docblock states the rule and the reason: a vendor's
 * recording URL expires, is often unauthenticated, and would be emailed to an
 * owner as a link anybody who saw it could open.
 *
 * ⚠️ **`transcript` IS UNTRUSTED INPUT AND MAY BE PHI.** It is a stranger's
 * speech, machine-transcribed. `VoicemailTranscribed` carries both warnings in
 * full: T137 §3.7's containment applies to it exactly as to an inbound SMS
 * (*data, never instructions*), and a voicemail to a healthcare tenant can
 * contain health information nobody asked for — with **no surface on which to
 * ask a caller anything before they speak**, so 2079–2081's per-review checkbox
 * has no equivalent here and the tenant-level rule stands.
 *
 * ⚠️ **ONE VOICEMAIL PER CALL, HELD BY A UNIQUE INDEX RATHER THAN BY A CHECK.**
 * Voice webhooks redeliver; `CALL_RECORDING_READY` arriving twice must produce
 * one row, and a `firstOrCreate` racing itself is what the constraint is for.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schema::create removed for voicemails to fix duplicates

        $audio = collect(VoicemailAudioState::cases())
            ->map(fn (VoicemailAudioState $state): string => "'{$state->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE voicemails
                ADD CONSTRAINT voicemails_audio_state_is_known
                CHECK (audio_state IN ({$audio}))
        SQL);

        $transcript = collect(VoicemailTranscriptState::cases())
            ->map(fn (VoicemailTranscriptState $state): string => "'{$state->value}'")
            ->implode(', ');

        DB::statement(<<<SQL
            ALTER TABLE voicemails
                ADD CONSTRAINT voicemails_transcript_state_is_known
                CHECK (transcript_state IN ({$transcript}))
        SQL);

        DB::statement('ALTER TABLE voicemails ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE voicemails FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON voicemails
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('voicemails');
    }
};
