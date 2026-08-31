<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The recipient's state — what `29` §2 rule 11's mini-TCPA half is missing.
 *
 * ⚠️ WHY A COLUMN RATHER THAN A DERIVATION. Rule 11 requires state rules to be
 * applied, and nothing in this schema could say which state anybody is in:
 * `customers` had no field for it, `locations.address` is one free-text string,
 * and `businesses.address` is the *business's* state rather than the
 * recipient's. Parsing a state out of an address line was rejected outright —
 * "Springfield" is in thirty-four states, and a guessed jurisdiction produces
 * confident compliance with the wrong statute, which CLAUDE.md names as the
 * reason the unset add-on price may not be inferred either.
 *
 * ⚠️ IT HAS NO WRITER TODAY, AND THAT IS STATED RATHER THAN LEFT TO BE FOUND.
 * Decision 403 found `display_on_website` dead — default false, no writer, and
 * the scope named for it never consulting it — so a column added with no writer
 * needs a better answer than "later". Here it is: the **reader exists and runs
 * on every send** (`ConsentService::stateRefusal()`), a null is a *refusal*
 * rather than a silently-permissive default, and both branches are exercised.
 * What is missing is upstream data collection — the CRM slice that captures a
 * structured customer address is the writer, and until it lands every marketing
 * send is refused with `SendRefusalReason::StateUnknown`.
 *
 * That costs nothing today: `24` §3.3 makes review requests transactional, and
 * they are the only messages this product sends.
 *
 * ⚠️ NOT NAMED `state`. `customers` already carries `lifecycle_stage`, and a
 * column called `state` on a CRM contact reads as a workflow status to every
 * person who meets it before reading this file. `region_code` says jurisdiction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('region_code', 2)->nullable()->after('phone');
        });

        // Uppercase USPS code or nothing. A lowercase 'fl' would miss every
        // `state_messaging_rules` lookup while looking perfectly populated —
        // the silent shape the whole scrubbing slice is built against, and the
        // same constraint `state_messaging_rules` carries on its own side so
        // the two cannot disagree about what a state code looks like.
        DB::statement(<<<'SQL'
            ALTER TABLE customers
                ADD CONSTRAINT customers_region_code_is_usps_code
                CHECK (region_code IS NULL OR region_code ~ '^[A-Z]{2}$')
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE customers DROP CONSTRAINT customers_region_code_is_usps_code');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn('region_code');
        });
    }
};
