<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `29` §19.6's escalation vocabulary gets exactly one source of truth, and it is
 * `urgent_terms` — decisions 6880–6890.
 *
 * ## What this column was
 *
 * `support_settings.emergency_keywords` shipped with the Stage 0 schema carrying
 * the comment *"Escalate immediately, including overnight (`29` §19.6)"* and a
 * database default of
 * `["emergency","flood","leak","no heat","burst","gas"]` on **every row**. In
 * `app/` it had a cast and a docblock. It had **no reader and no writer** — the
 * only thing that ever wrote it was `SupportSettingFactory`. 2912 already listed
 * it among this table's writerless columns.
 *
 * ⛔ **IT IS `businesses.pixel_tenant_id`'S SHAPE EXACTLY** (`CLAUDE.md`, 272):
 * a plausible seeded value, a factory writing it, a cast, a docblock, **and a
 * spec citation by section number** — *"a writerless column can be worse than
 * missing, it looks like the answer."* The next person to build the voicemail
 * escalation would grep §19.6, find the one column in the schema that cites it,
 * and wire it up.
 *
 * ## Why `urgent_terms` owns the rule and this does not
 *
 * ⚠️ **THE TWO ARE NOT MERELY DUPLICATIVE — THEY TAKE OPPOSITE SIDES OF AN
 * ARGUMENT THAT WAS ALREADY HAD AND WRITTEN DOWN.** `urgent_terms`' own
 * migration refuses a seeded list in terms: *"urgency is a fact about a trade,
 * not about the English language. 'No heat' is an emergency for a heating
 * engineer and a Tuesday for a locksmith… a seeded platform list would page a
 * business's owner at 3am over a word they never chose, and the second time that
 * happens they stop reading the pings — which disarms the escalation for the
 * terms they did choose."* This column seeded precisely that list, as a row
 * default, for every business ever provisioned.
 *
 * Everything that makes an escalation trustworthy is on the other side:
 * word-boundary matching and its "Bleak Street" defence, untrusted-input
 * handling, `UrgentTerms::MAX_TERMS`, a chokepoint lint, an owner's screen, a
 * policy, an audit row, and an owner SMS + email that is deliberately **not**
 * gated on the AI balance. This side had a jsonb array and a cast.
 *
 * ⚠️ **ONE BUSINESS GETS ONE VOCABULARY OF URGENCY, NOT ONE PER CHANNEL.** The
 * owner is asked one question — *which words mean drop everything?* — and its
 * answer cannot depend on whether the customer typed or spoke. Two lists is a
 * support surface (`CLAUDE.md`'s first tiebreaker) and a product in which the
 * words on the owner's screen behave differently down the phone.
 *
 * ⛔ **DELETED RATHER THAN COMMENTED AS SUPERSEDED**, because 272's own lesson is
 * that the comment does not stop the next person: `plugins.embed_key`'s docblock
 * cited `pixel_tenant_id` as the pattern to copy and it was still wrong. A
 * "do not use this" note on a seeded, cast, factoried column is weaker than the
 * column not being there.
 *
 * ## ⚠️ What deleting it costs, and what pays for it
 *
 * This was the only place in the schema carrying §19.6's escalation clause, so
 * removing it removes the pointer a future voice lane would grep for. That is
 * paid for twice and neither is a comment: the clause is restated on
 * `urgent_terms` where the mechanism actually lives, and
 * `tests/Feature/Architecture/PricesTest.php` now fails the build on any second
 * store of urgency words anywhere in the schema.
 *
 * ⛔ **THE VOICE HALF OF §19.6 IS STILL NOT BUILT AND THIS MIGRATION DOES NOT
 * BUILD IT** (6891). `VoicemailTranscribed` reaches zero listeners, so a
 * voicemail saying *"gas leak"* still escalates nowhere. Deleting this column
 * does not make that worse — nothing read it — and it is recorded here so the
 * deletion is never mistaken for the fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_settings', function (Blueprint $table): void {
            $table->dropColumn('emergency_keywords');
        });
    }

    /**
     * ⚠️ **THE COLUMN COMES BACK WITHOUT THE SEEDED LIST AND WITHOUT THE §19.6
     * CITATION.** A `down()` that restored the default would restore the defect —
     * six urgent words nobody chose, on every row — and a rollback is not a
     * decision to re-open a ruling. What it restores is the shape of the column,
     * which is all a rollback owes.
     */
    public function down(): void
    {
        Schema::table('support_settings', function (Blueprint $table): void {
            $table->jsonb('emergency_keywords')->default('[]');
        });
    }
};
