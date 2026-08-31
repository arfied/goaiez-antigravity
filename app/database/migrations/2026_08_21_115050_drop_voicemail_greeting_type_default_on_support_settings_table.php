<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `support_settings.voicemail_greeting_type` stops asserting an answer nobody
 * gave — decisions 6892–6895.
 *
 * ## The column as it stood
 *
 * `NOT NULL DEFAULT 'tts'`, with **no reader and no writer** anywhere in `app/`
 * (2912 lists it; `VoiceGreeting`'s docblock names it as owed). So every value
 * this column has ever held is the default, and no business has ever chosen one.
 *
 * ⛔ **AND THE DEFAULT IS A FORBIDDEN INSTRUCTION UNDER ONE OF ITS TWO LIVE
 * READINGS.** `CLAUDE.md` forbids a *"TTS call during a call"* outright —
 * greetings are pre-rendered static files. Read as *"synthesise this greeting
 * when the call arrives"*, `'tts'` is the thing the rule prohibits, sitting in
 * the database as the platform's answer.
 *
 * ⚠️ **THE OTHER READING IS INNOCENT AND IS ALSO `CLAUDE.md`'s**: the stack notes
 * say *"pre-rendered TTS greetings as 8kHz μ-law WAV in R2"*, so `'tts'` can mean
 * *"a greeting we produced with text-to-speech, ahead of time"*, which is exactly
 * what the platform does. **Both readings are in the file every session loads,
 * and nothing in this repository chooses between them** — the column has no enum,
 * so its legal vocabulary is written down nowhere at all.
 *
 * ## ⛔ WHY THE DEFAULT GOES AND THE COLUMN STAYS
 *
 * Dropping the column would settle a question that is not this slice's: if the
 * innocent reading is the intended one, the column is how an owner-recorded
 * greeting is told apart from a platform-produced one, and destroying that
 * distinction on the strength of the guilty reading would be guessing.
 * Keeping `'tts'` blesses the guilty reading for whoever writes the first
 * reader — and **a forbidden default is worse than an absent one, because they
 * inherit it rather than choose it.**
 *
 * So the column keeps its shape and loses its answer. `null` is *"nobody has
 * chosen"*, which is the only true statement available, and it is true under
 * both readings. Existing rows are nulled for the same reason: every one of them
 * holds the default rather than a decision, and leaving the string in place
 * would leave the guilty reading asserted per-row after removing it per-table.
 *
 * ⚠️ **2912's INVARIANT SURVIVES IN THE FORM THAT MATTERS.** That decision refused
 * to seed a row because *"every column on this table already has a database
 * default that is the safe answer"* — the point being that a tenant with a row
 * and a tenant without one read identically. They still do: both now read `null`
 * here. What changes is that for this one column **the safe answer is no answer**.
 *
 * ⛔ **THE VOCABULARY IS STILL OWED AND IS THE VOICE LANE'S** (6895). Whoever
 * writes the first reader owes a backed enum in `app/Enums` — never a database
 * enum — and owes the ruling on which reading of `'tts'` was meant. This
 * migration deliberately does not pre-empt either.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE support_settings ALTER COLUMN voicemail_greeting_type DROP DEFAULT');
        DB::statement('ALTER TABLE support_settings ALTER COLUMN voicemail_greeting_type DROP NOT NULL');

        // ⚠️ **EVERY EXISTING VALUE IS THE DEFAULT, BECAUSE THE COLUMN HAS NEVER
        // HAD A WRITER.** So this destroys no choice anybody made. It is scoped to
        // the literal default rather than blanking the column, so that if a writer
        // lands between this being written and this being run, what it wrote
        // survives.
        DB::table('support_settings')->where('voicemail_greeting_type', 'tts')->update([
            'voicemail_greeting_type' => null,
        ]);
    }

    /**
     * ⚠️ **THE ROLLBACK RESTORES THE COLUMN'S SHAPE, NOT THE FORBIDDEN DEFAULT.**
     * Same rule as the migration beside it: a rollback undoes a schema change and
     * is not a decision to reinstate a value this slice argued against.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE support_settings ALTER COLUMN voicemail_greeting_type DROP NOT NULL');
    }
};
