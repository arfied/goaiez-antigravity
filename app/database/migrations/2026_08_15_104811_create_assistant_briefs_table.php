<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The two single lines a business gives its assistant — T176 P5, §2.4.
 *
 * §2.4's "Teach your assistant" step asks for two things that are one value
 * each rather than a list: the **quote disclaimer line** skill 4 states with
 * every price, and the **emergency line** skill 9 gives out when it escalates.
 * The lists themselves are `price_list_items` and `urgent_terms`.
 *
 * ## Why one row rather than two near-empty parent tables
 *
 * ⚠️ **THE ALTERNATIVE WAS A `price_lists` PARENT AND AN `urgent_term_lists`
 * PARENT, EACH HOLDING ONE COLUMN.** Both would be exactly one row per business
 * for the lifetime of the product, joined to on every read, so the schema would
 * carry two tables to say what one row says. What made the coupling worth
 * keeping is not the storage — it is the **contract**, and that is where it
 * lives: `PriceList` is a DTO carrying the disclaimer *and* the entries, so a
 * caller cannot obtain a figure to quote without holding the line it must be
 * quoted with. Splitting the tables would not have made that any truer, and
 * joining them here does not make it any less true.
 *
 * ## Both columns are nullable, and null is not "empty"
 *
 * ⚠️ **`quote_disclaimer` NULL MEANS *THE PLATFORM'S WORDS*, NOT *NO
 * DISCLAIMER*.** §2.4 asks for a seeded default, and the seed lives in the
 * registry (`assistant.quote_disclaimer`) rather than being copied into this
 * column at provisioning. That is `reviews.default_invite_threshold`'s shape
 * (1420): a tenant who has written their own has a stored line Ops cannot move,
 * and a tenant who has not follows the platform when we improve the wording. A
 * copy-at-provision default would freeze today's sentence into every account
 * ever opened.
 *
 * ⚠️ **`emergency_line` NULL MEANS THE SKILL SAYS NOTHING**, which is R13:
 * skill 9 escalates to the owner either way and *"gives the tenant's emergency
 * line if set"*. There is no number that is right for a business which has not
 * given us one, and the one thing worse than not having it is inventing it.
 *
 * ⛔ **NOTHING SEEDS THIS TABLE.** A row appears the first time somebody answers
 * one of the two questions, and its absence is the ordinary state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_briefs', function (Blueprint $table): void {
            $table->id();

            // ⛔ UNIQUE RATHER THAN INDEXED. Two briefs for one business would
            // be two disclaimers, and which one a customer was told would depend
            // on row order — the same argument `tenant_links`' partial unique
            // index makes for the booking link.
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();

            // The line skill 4 states with every price ("Final price confirmed
            // on site"). ⚠️ **UNTRUSTED**: it is typed by a business and then
            // travels into a model prompt beside a member of the public's
            // message, exactly as `tenant_links.label` does. Rail 1's fence is
            // what handles it there; nothing edits it here.
            $table->text('quote_disclaimer')->nullable();

            // E.164, normalised by `Identifier` before it reaches this column so
            // that what is stored is what a phone will dial.
            $table->string('emergency_line', 20)->nullable();

            $table->timestamps();
        });

        // A blank line is a third state between "the platform's words" and "the
        // business's own words", and it is the one that renders as an empty
        // sentence in the middle of a text message.
        DB::statement(<<<'SQL'
            ALTER TABLE assistant_briefs
                ADD CONSTRAINT assistant_briefs_lines_are_said_or_absent
                CHECK (
                    (quote_disclaimer IS NULL OR btrim(quote_disclaimer) <> '')
                    AND (emergency_line IS NULL OR btrim(emergency_line) <> '')
                )
        SQL);

        DB::statement('ALTER TABLE assistant_briefs ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE assistant_briefs FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON assistant_briefs
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_briefs');
    }
};
