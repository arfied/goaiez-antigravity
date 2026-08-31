<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic top-up: the standing arrangement, and the charges made under it.
 *
 * The charge itself already works — `CreditTopUps::chargeStoredCard()` bills the
 * card on file for a top-up SKU. What did not exist is the *agreement* that makes
 * it fire without anybody asking, which is the part that spends money on its own.
 *
 * ## Opt-in, and nothing here creates an arrangement
 *
 * ⛔ **`credits.auto_topup.enabled_by_default` SEEDS `false` AND THIS MIGRATION
 * SEEDS NOTHING** (decision 3306, restated by the owner as "disabled by default"
 * on 2026-08-14). An account that has never chosen has **no row here** and is
 * never charged automatically. There is deliberately no backfill, no provisioning
 * hook and no default row: **a recurring charge nobody agreed to is the worst
 * thing this feature can do**, worse than the feature not existing. The absence
 * of a row is the off state, which is the only off state that cannot be flipped
 * by a bad default.
 *
 * ## The agreement is a record, not a boolean
 *
 * `CLAUDE.md` reserves CONFIRM for three things and *"anything that spends
 * money"* is one. 2064 puts CONFIRM on **the arrangement rather than each
 * charge** — that is what an arrangement *is*: agreement once to a standing
 * instruction. So the four confirmation columns are NOT NULL, on the same
 * reasoning as every consent record in this schema: the question a chargeback
 * asks is *what did you show them*, and a boolean cannot answer it.
 *
 * ⚠️ **`agreed_amount_cents` AND `ceiling_cents` ARE BOTH STORED BECAUSE THEY ARE
 * BOTH THINGS THE TENANT AGREED TO.** Prices are admin-editable (3415), so the
 * per-charge figure really can move after the agreement — and a charge larger
 * than the one they saw is a charge they did not agree to. `AutoTopUps` refuses
 * it rather than proceeding.
 *
 * ## `auto_topup_charges` exists so nothing has to be added to `credit_purchases`
 *
 * The ceiling needs *how much has this arrangement spent this month*, and the
 * trigger needs *is there already an unsettled charge for this product*. Both are
 * questions about purchases made **automatically**, and `credit_purchases` has no
 * column saying which those were. ⚠️ **Adding one would have meant editing
 * `CreditPurchases::open()`**, which parallel lanes were inside; a link table
 * answers both questions without touching the funder, and keeps the
 * automatic-spend history legible on its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_topup_arrangements', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // Strings cast to App\Enums\* — never a Postgres enum type
            // (`CLAUDE.md`, decision 863), with a CHECK below for 216's second
            // layer.
            $table->string('product');
            $table->string('tier');

            // ⛔ THE CEILING THE OWNER RULED ON (3306): "the most an account will
            // automatically be charged for top-ups before automatic top-up
            // stops". Integer cents plus currency, `18` §Money handling.
            //
            // ⚠️ THE WINDOW IT APPLIES OVER IS THE CALENDAR MONTH, AND THE RULING
            // DOES NOT SAY. "The most before it stops" is silent between
            // per-month, per-billing-period and lifetime, and the three differ
            // enormously — lifetime would stop a paying account for ever after
            // $50. The calendar month is taken **for consistency with 3440**,
            // where the owner chose the calendar month for the credit reset over
            // the billing period. A ceiling on a different clock from the grant it
            // tops up would be two calendars in one feature, and the support call
            // would be unanswerable.
            $table->integer('ceiling_cents');
            $table->string('currency', 3);

            // The CONFIRM record for the arrangement. All four NOT NULL.
            $table->string('agreed_actor');
            $table->integer('agreed_amount_cents');
            $table->string('agreement_wording');
            $table->timestamp('agreed_at');

            // ⛔ A DECLINED CARD ON A SCHEDULE IS A FRAUD ALARM AND A SUPPORT
            // CALL. Retrying for ever against a card the bank is refusing is how
            // an account gets flagged, so failures are counted and the
            // arrangement suspends itself. Suspended is not cancelled: the
            // agreement stands, and fixing the card resumes it.
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('stopped_at')->nullable();
            $table->string('stopped_reason')->nullable();

            // Cancelled by the tenant. Kept rather than deleted, because "did they
            // ever agree to this" is a question asked about a period that has
            // already passed.
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // RLS predicates business_id on every query and Postgres does not
            // index a foreign key automatically — the MySQL habit that does not
            // transfer, and the omission 314–316 caught on `destination_clicks`.
            $table->index(['business_id', 'product', 'cancelled_at']);
        });

        Schema::create('auto_topup_charges', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auto_topup_arrangement_id')->constrained()->cascadeOnDelete();

            // ⚠️ UNIQUE, AND IT IS **NOT** WHAT MAKES THE TRIGGER IDEMPOTENT —
            // 314–316's rule is to write the claim after the mechanism, and the
            // mechanism is one file over. This index refuses two charge rows
            // pointing at ONE purchase; what a second scheduler run would produce
            // is a SECOND purchase with a second id, which this index is happy to
            // accept. `AutoTopUps::hasChargeInFlight()` is what refuses that, by
            // asking whether any charge under this arrangement still has a
            // purchase in a state that means money is in flight.
            //
            // What the index does protect is the ceiling arithmetic: the same
            // purchase counted twice would spend a tenant's monthly limit at twice
            // the rate they agreed to.
            $table->foreignId('credit_purchase_id')->unique()->constrained()->cascadeOnDelete();

            // What it charged, copied so the ceiling arithmetic does not depend on
            // a price that has since moved.
            $table->integer('price_cents');
            $table->string('currency', 3);

            $table->timestamps();

            // The ceiling's own query: this arrangement's charges inside a window.
            $table->index(['business_id', 'auto_topup_arrangement_id', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE auto_topup_arrangements
                ADD CONSTRAINT auto_topup_arrangements_product_is_known
                CHECK (product IN ('sms', 'email', 'ai'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE auto_topup_arrangements
                ADD CONSTRAINT auto_topup_arrangements_tier_is_known
                CHECK (tier IN ('automatic', 'manual'))
        SQL);

        // ⚠️ A CEILING OF ZERO IS AN ARRANGEMENT THAT CAN NEVER FIRE — a disabled
        // arrangement wearing an enabled one's clothes, reading as working on
        // every screen. Cancelling is how you turn it off.
        DB::statement(<<<'SQL'
            ALTER TABLE auto_topup_arrangements
                ADD CONSTRAINT auto_topup_arrangements_ceiling_is_spendable
                CHECK (ceiling_cents > 0)
        SQL);

        // The agreement has to be agreement to something. Same shape as
        // `credit_purchases_confirmation_is_whole`, one table over.
        DB::statement(<<<'SQL'
            ALTER TABLE auto_topup_arrangements
                ADD CONSTRAINT auto_topup_arrangements_agreement_is_whole
                CHECK (
                    agreed_amount_cents > 0
                    AND btrim(agreed_actor) <> ''
                    AND btrim(agreement_wording) <> ''
                )
        SQL);

        // A suspension nobody can explain is the row support is asked about and
        // cannot answer — `credit_ledger_adjustment_is_explained`'s reasoning.
        DB::statement(<<<'SQL'
            ALTER TABLE auto_topup_arrangements
                ADD CONSTRAINT auto_topup_arrangements_stop_is_explained
                CHECK (
                    (stopped_at IS NULL)
                    OR (stopped_reason IS NOT NULL AND btrim(stopped_reason) <> '')
                )
        SQL);

        // ⛔ ONE LIVE ARRANGEMENT PER PRODUCT. Two would each read the same
        // balance, each decide it was low, and each charge — the ceiling enforced
        // twice over and honoured once. Partial, so a cancelled arrangement stays
        // on the record and a tenant may agree again.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX auto_topup_arrangements_one_live_per_product
                ON auto_topup_arrangements (business_id, product)
                WHERE cancelled_at IS NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE auto_topup_charges
                ADD CONSTRAINT auto_topup_charges_charged_something
                CHECK (price_cents > 0)
        SQL);

        foreach (['auto_topup_arrangements', 'auto_topup_charges'] as $name) {
            DB::statement("ALTER TABLE {$name} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$name} FORCE ROW LEVEL SECURITY");

            DB::statement(<<<SQL
                CREATE POLICY tenant_isolation ON {$name}
                    USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                    WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_topup_charges');
        Schema::dropIfExists('auto_topup_arrangements');
    }
};
