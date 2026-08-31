<?php

declare(strict_types=1);

use App\Enums\OptOutScope;
use App\Enums\SuppressionReason;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The way back — `ConsentService::suppress()`'s own deferred half.
 *
 * That method has said since it was written: *"THERE IS NO WAY BACK, and that is
 * deliberate for now. Carrier rules require honouring START as well as STOP, so
 * row 4 will need a resume path — and it must go through this service and write
 * an audit row, because deleting a suppression_list row directly reverses a
 * withdrawal with no record that anybody did it."* This is that row 4.
 *
 * ⚠️ A LIFT IS AN INSERT, NEVER AN UPDATE AND NEVER A DELETE. `opt_outs` refuses
 * both in the model, and for the stated reason: deleting a refusal does not
 * resubscribe anybody, it makes the platform forget it was told to stop. So the
 * reversal is its own row in its own table, and the evidence of the refusal
 * survives it — which is the only arrangement in which "we un-suppressed this
 * person on the 6th, on a START from their handset" is answerable at all.
 *
 * ⚠️ AND THE HARD PART IS NOT THE LIFT — IT IS THE SECOND STOP. Both suppression
 * stores are written with `firstOrCreate` against a unique key, because a retried
 * carrier webhook is the ordinary case and a raised exception on a STOP path is a
 * dropped STOP. That idempotency turns poisonous the moment a lift exists: STOP,
 * lift, **STOP again** finds the original row still there, writes nothing, and a
 * lift recorded against that key would go on clearing it forever. The second STOP
 * would be accepted, logged, and have no effect — which is the exact failure
 * `opt_outs` was created to prevent, rebuilt by the feature meant to complete it.
 *
 * `lift_generation` is what stops it, and it is a counter rather than a timestamp
 * deliberately. Timestamps cannot express this: the re-STOP writes no row at all,
 * so there is no new `created_at` to compare, and at second granularity a STOP
 * and a lift in the same second would need a tie-break whose safe direction is
 * unprovable. A generation makes "is this refusal still standing" an anti-join on
 * an exact integer — a suppression at generation N is live until a lift exists at
 * generation N, and the next STOP writes generation N+1.
 *
 * ⚠️ AND THIS IS THE ONE TABLE IN THE SUPPRESSION SET AN `APP_KEY` ROTATION
 * BREAKS IN THE SAFE DIRECTION, WHICH IS WORTH KNOWING BECAUSE IT IS NOT
 * OBVIOUS FROM EITHER SIDE. `suppression_list` matches on the identifier **in
 * clear**, so a tenant-recorded withdrawal keeps refusing through a rotation —
 * the privacy-worst table is the availability-best one. But the lift that
 * released somebody is keyed on `value_hash` like everything else here, so after
 * a rotation `ConsentService::standingPredicate()`'s anti-join finds no lift and
 * every previously released contact is silently refused again. It is
 * repairable, and only because of the clear identifier: `lift()` finds the
 * `suppression_list` row by it and reuses that row's `lift_generation`, so a
 * fresh lift written after the rotation clears the entry. ⛔ **`opt_outs` has
 * neither half** — see its own migration, and `.claude/skills/deploying/` for
 * the operator's account of what a rotation costs across all of these.
 *
 * ⚠️ AND SINCE 8080 THE REPAIR ABOVE IS NOT SOMETHING AN OPERATOR REACHES ON
 * THEIR OWN, BECAUSE THE PLATFORM STOPS SENDING FIRST (8192).
 * `identifier_hash_epochs` fingerprints the hashing function and
 * `ConsentService::decide()` refuses every send with `SuppressionUnreadable`
 * while it does not match — so the over-suppression this paragraph describes is
 * reached only *after* the key is restored or the loss is accepted through
 * `consent:hash-epoch`. ⚠️ A ROW HERE COUNTS TOWARDS *"is anything stored"*, so
 * an install holding lifts and no epoch is `Unattributed` and refuses rather
 * than reading as a fresh checkout (8183).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppression_lifts', function (Blueprint $table): void {
            $table->id();

            // Platform-shaped, exactly like `opt_outs`: nullable business_id, no
            // RLS, no tenant trait. A lift has to be answerable on a path where
            // no tenant is resolved, because the refusal it reverses was.
            $table->string('scope')->default(OptOutScope::Platform->value);
            $table->foreignId('business_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('identifier_type');

            // ⚠️ The hash, never the identifier — `opt_outs`' rule, and for a
            // sharper reason here. A table of identifiers that were suppressed
            // *and then released* is a list of people who are contactable again
            // and have already engaged with a local business, which is a more
            // valuable marketing list than the register it reverses.
            $table->string('value_hash', 64);

            // Which refusal this clears. Matched exactly against the suppression
            // row's own `lift_generation`; see the class docblock.
            $table->unsignedInteger('generation');

            // carrier_start | operator_action, cast to LiftSource. There is no
            // case for a consent capture, which is the whole point of the enum.
            $table->string('source');

            // Required, never nullable — 297's rule, and the only control at all
            // on an operator lift.
            $table->string('actor');

            // Free text: the carrier keyword, the ticket reference, the words the
            // customer used. Never the identifier.
            $table->text('note')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['identifier_type', 'value_hash']);
        });

        // A retried START is the ordinary case, the same as a retried STOP, so
        // lifting is idempotent by construction rather than by a caller's guard.
        // NULLS NOT DISTINCT for the same reason `opt_outs` needs it: platform
        // rows carry a NULL business_id, and Postgres would otherwise treat every
        // one of them as unique and let a webhook retry write a row per delivery.
        DB::statement(<<<'SQL'
            ALTER TABLE suppression_lifts
                ADD CONSTRAINT suppression_lifts_scope_business_type_value_gen_unique
                UNIQUE NULLS NOT DISTINCT (scope, business_id, identifier_type, value_hash, generation)
        SQL);

        // `opt_outs`' own rule, and it has to hold here too or a lift's scope
        // stops matching the refusal it claims to clear.
        DB::statement(<<<'SQL'
            ALTER TABLE suppression_lifts
                ADD CONSTRAINT suppression_lifts_scope_matches_business
                CHECK (
                    (scope = 'platform' AND business_id IS NULL)
                    OR (scope = 'tenant' AND business_id IS NOT NULL)
                )
        SQL);

        // ------------------------------------------------------------------
        // Both suppression stores gain the generation and the reason class.
        // ------------------------------------------------------------------

        // ⚠️ DDL WITH A DEFAULT, NEVER AN `UPDATE` — decision 319. Every
        // tenant-owned table is RLS-`FORCE`d, migrations run as the table owner,
        // and an UPDATE here would read `suppression_list` with no
        // `app.business_id` set, match zero rows, and report success. `ADD COLUMN
        // ... DEFAULT` is catalogue work and touches no rows through the policy,
        // so it is the shape that actually backfills. 685's backfill had to
        // suspend FORCE for one statement to avoid the same trap.
        //
        // The default is `stop` and it is correct rather than convenient:
        // `ConsentService::suppress()` is the only writer either table has ever
        // had, it has no production caller at all, and a STOP is the only thing
        // it could have recorded. Nothing else could be in here.
        Schema::table('suppression_list', function (Blueprint $table): void {
            $table->string('reason_class')->default(SuppressionReason::Stop->value);
            $table->unsignedInteger('lift_generation')->default(0);
        });

        Schema::table('opt_outs', function (Blueprint $table): void {
            $table->string('reason_class')->default(SuppressionReason::Stop->value);
            $table->unsignedInteger('lift_generation')->default(0);
        });

        // ⚠️ THE UNIQUE KEYS MUST TAKE THE GENERATION OR THE SECOND STOP IS LOST.
        // Without it `firstOrCreate` finds the generation-0 row after a lift and
        // writes nothing, and the generation-0 lift goes on clearing it forever.
        // Rewritten rather than added alongside: two unique constraints would
        // leave the narrower one still refusing the insert.
        DB::statement('ALTER TABLE suppression_list DROP CONSTRAINT suppression_list_business_id_channel_identifier_unique');

        DB::statement(<<<'SQL'
            ALTER TABLE suppression_list
                ADD CONSTRAINT suppression_list_business_channel_identifier_gen_unique
                UNIQUE (business_id, channel, identifier, lift_generation)
        SQL);

        DB::statement('ALTER TABLE opt_outs DROP CONSTRAINT opt_outs_scope_business_type_value_unique');

        DB::statement(<<<'SQL'
            ALTER TABLE opt_outs
                ADD CONSTRAINT opt_outs_scope_business_type_value_unique
                UNIQUE NULLS NOT DISTINCT (scope, business_id, identifier_type, value_hash, lift_generation)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('suppression_lifts');

        DB::statement('ALTER TABLE suppression_list DROP CONSTRAINT suppression_list_business_channel_identifier_gen_unique');
        DB::statement('ALTER TABLE opt_outs DROP CONSTRAINT opt_outs_scope_business_type_value_unique');

        Schema::table('suppression_list', function (Blueprint $table): void {
            $table->dropColumn(['reason_class', 'lift_generation']);
        });

        Schema::table('opt_outs', function (Blueprint $table): void {
            $table->dropColumn(['reason_class', 'lift_generation']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE suppression_list
                ADD CONSTRAINT suppression_list_business_id_channel_identifier_unique
                UNIQUE (business_id, channel, identifier)
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE opt_outs
                ADD CONSTRAINT opt_outs_scope_business_type_value_unique
                UNIQUE NULLS NOT DISTINCT (scope, business_id, identifier_type, value_hash)
        SQL);
    }
};
