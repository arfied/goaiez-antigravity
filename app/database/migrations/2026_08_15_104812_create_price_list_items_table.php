<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a business charges for the things it does — T176 P5, R13's skill 4.
 *
 * R13: *"quote **only** list items; give the tenant's price or range + the
 * tenant's disclaimer line; off-list → never invent."* This table is the whole
 * of "list items". Nothing outside it is quotable, and an empty table means
 * skill 4 is **absent** rather than free-handed — the same reading `tenant_links`
 * takes of a missing booking URL.
 *
 * ## `confirmed_at` is the review-before-live gate, and it is a timestamp
 *
 * ⛔ **NOTHING INGESTED FROM A DOCUMENT IS QUOTABLE UNTIL A PERSON CONFIRMS
 * IT.** A row read off an uploaded price sheet lands with `confirmed_at` NULL
 * and `source = 'document'`; a row somebody typed lands confirmed, because
 * typing a price *is* the review. The reader (`PriceBook::list()`) filters on
 * this column, and `PriceList` refuses to be constructed from an unconfirmed
 * entry at all — two layers, because a `where` is the sort of thing a later
 * query forgets and a constructor is not.
 *
 * ⚠️ **A TIMESTAMP RATHER THAN A BOOLEAN**, on `gating_ack_at`'s only surviving
 * lesson: *when* a figure became quotable is the question anybody asks after a
 * customer is quoted the wrong number, and a boolean cannot answer it.
 *
 * ## A price is a figure or a range, and the shape is derived rather than stored
 *
 * `amount_cents` alone is a figure. `amount_cents` with `amount_max_cents` is a
 * range. ⛔ **THERE IS NO `kind` COLUMN AND THERE MUST NOT BE**: a stored shape
 * is a second source of truth that can disagree with the two numbers beside it,
 * and the disagreement would surface as an assistant quoting "£85 to £85".
 * The CHECK below makes a degenerate range unstorable, so the derivation cannot
 * be wrong.
 *
 * ## Money
 *
 * Integer minor units plus an ISO 4217 code, `18` §Money handling, and the code
 * travels with the figure rather than sitting in configuration — the same call
 * `tenant_links.fee_currency` made. ⚠️ **`amount_cents >= 0` and zero is
 * allowed**: "we do that free" is a thing a business says, and it is a price.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_list_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // What the business calls the job. ⚠️ **UNTRUSTED** — typed by a
            // tenant or read out of a tenant's own document, and then placed in
            // a model prompt beside a member of the public's message. Rail 1's
            // fence is what handles it there; nothing edits it here, on
            // `PromptFence`'s own reasoning that a silent edit means the model
            // judged something the person did not write.
            $table->string('label', 120);

            // How skill 4 addresses one item among many, derived from the label
            // rather than typed — `tenant_links.slug`'s argument exactly.
            $table->string('slug', 160);

            // A string cast to App\Enums\PriceListItemSource — never a Postgres
            // enum type (`CLAUDE.md`, decision 863), with a CHECK below.
            $table->string('source');

            $table->integer('amount_cents');

            // NULL for a figure; the top of the range when there is one.
            $table->integer('amount_max_cents')->nullable();

            $table->string('currency', 3);

            // NULL until a person says this may be quoted. See the class
            // docblock — this column is the whole review-before-live gate.
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            // RLS predicates `business_id` on every query and Postgres does not
            // index a foreign key automatically — the MySQL habit that does not
            // transfer. `confirmed_at` rides along because both of the two reads
            // this table has filter on it.
            $table->index(['business_id', 'confirmed_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
                ADD CONSTRAINT price_list_items_source_is_known
                CHECK (source IN ('manual', 'document'))
        SQL);

        // An item with no name is one skill 4 can never be asked for, and a
        // blank slug is one it can never be addressed by.
        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
                ADD CONSTRAINT price_list_items_is_nameable
                CHECK (btrim(label) <> '' AND btrim(slug) <> '')
        SQL);

        // ⛔ A NEGATIVE PRICE IS NOT A PRICE, AND A RANGE THAT DOES NOT GO
        // UPWARDS IS A FIGURE WRITTEN TWICE. The second half is what keeps
        // "figure or range" derivable from the two columns rather than needing a
        // third that could disagree with them.
        DB::statement(<<<'SQL'
            ALTER TABLE price_list_items
                ADD CONSTRAINT price_list_items_amounts_make_a_price
                CHECK (
                    amount_cents >= 0
                    AND (amount_max_cents IS NULL OR amount_max_cents > amount_cents)
                )
        SQL);

        // ⛔ ONE ROW PER NAME PER BUSINESS. Two items answering to "Front door
        // lockout" would make `quote('front-door-lockout')` return whichever the
        // planner happened to order first — a price a customer is told that
        // nobody chose. It is deliberately **not** unique across the platform:
        // every locksmith on earth prices a lockout.
        //
        // ⚠️ **AND IT SPANS THE UNCONFIRMED ROWS TOO, WHICH IS THE POINT.** An
        // ingest that could insert a second "Front door lockout" beside a live
        // one would let an unreviewed figure sit under the same name as a
        // reviewed one; instead the write fails and `PriceBook` reports the row
        // as one the business already prices.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX price_list_items_slug_is_unique_per_tenant
                ON price_list_items (business_id, slug)
        SQL);

        DB::statement('ALTER TABLE price_list_items ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE price_list_items FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON price_list_items
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('price_list_items');
    }
};
