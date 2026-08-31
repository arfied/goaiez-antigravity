<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The links a business gives its assistant to hand out — T176 P6, R12–R14.
 *
 * R12's links-and-conversation law is why this is one small table rather than an
 * integrations schema: *"the agent has no hooks into any tenant system — no
 * calendar API, no payment gateway, no job tracker. Booking = send the tenant's
 * own booking URL."* Every row here is a URL the business already owns.
 *
 * ## The absence of a row is the answer, and R13 is why that matters
 *
 * ⛔ **NOTHING SEEDS THIS TABLE AND NOTHING DEFAULTS A ROW INTO IT.** R13's
 * capability-gating law makes a missing link mean *the skill is absent* — the
 * agent captures preferred times and hands off rather than inventing an
 * appointment. A seeded placeholder row would be a grounded skill pointing at a
 * URL nobody chose, which is the one failure R13 exists to prevent: the agent
 * being told it can book while holding nothing to book with.
 *
 * ## The columns that are only true for one kind
 *
 * `slug`, `fee_cents`, `fee_currency` and `fee_covers` are each meaningful for
 * exactly one kind, and the CHECKs below say so rather than leaving it to the
 * writer. ⚠️ **A fee on a booking link would be a call-out charge the assistant
 * quotes from a row nothing reads**, and a document with no slug is a document
 * skill 7 can never name.
 *
 * ⚠️ **`fee_cents = 0` IS A DELIBERATE ANSWER AND THE CHECK ALLOWS IT.** "We come
 * out free" is a thing a locksmith says, and R13's distinction is *set* against
 * *unset* rather than truthy against falsy — `TenantLink::groundsFeeCollection()`
 * carries the same distinction one layer up, with its own test.
 *
 * ## What is unique, and what deliberately is not
 *
 * One booking link and one payment link per business, enforced by a partial
 * unique index rather than by the writer — `TenantLinkKind::isMultiple()` says
 * the same thing in PHP and 216's second layer is what makes it true when a
 * second writer appears. Documents are unique on their slug per business,
 * because the slug is what skill 7 addresses one by, and two rows answering to
 * one name would make `document($slug)` return whichever the planner happened to
 * order first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_links', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // A string cast to App\Enums\TenantLinkKind — never a Postgres enum
            // type (`CLAUDE.md`, decision 863), with a CHECK below.
            $table->string('kind');

            // What the business calls it. ⚠️ **UNTRUSTED**: it is typed by a
            // tenant and then travels into a model prompt beside a member of the
            // public's message — `TenantLink`'s docblock carries the argument,
            // and rail 1's fence is what handles it there.
            $table->string('label');

            // The business's own URL. ⛔ **NEVER SENT DIRECTLY** — R14 puts every
            // agent-sent link through a per-send short link, which is why the DTO
            // built from this column keeps it behind a method rather than on a
            // public property.
            //
            // `text` rather than `string`: a booking link out of a scheduler
            // carries query parameters, and a 255-cap truncation would silently
            // produce a working-looking link that lands on the wrong page.
            $table->text('destination');

            // How skill 7 names one document among several. NULL for the
            // single-valued kinds — see the CHECK.
            $table->string('slug')->nullable();

            // The call-out fee, integer cents plus its currency (`18` §Money
            // handling). Both nullable together: no fee set is what switches
            // skill 6 off, and it is not the same as a fee of zero.
            $table->integer('fee_cents')->nullable();
            $table->string('fee_currency', 3)->nullable();

            // The what-it-covers line §2.4 asks for and skill 6 states beside the
            // figure. ⚠️ Untrusted for the same reason `label` is.
            $table->text('fee_covers')->nullable();

            $table->timestamps();

            // RLS predicates `business_id` on every query and Postgres does not
            // index a foreign key automatically — the MySQL habit that does not
            // transfer.
            $table->index(['business_id', 'kind']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenant_links
                ADD CONSTRAINT tenant_links_kind_is_known
                CHECK (kind IN ('booking', 'payment', 'document'))
        SQL);

        // A link with no destination and a link with no name are both rows that
        // read as configured on every screen and send nothing anybody can use.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_links
                ADD CONSTRAINT tenant_links_is_nameable_and_reachable
                CHECK (btrim(label) <> '' AND btrim(destination) <> '')
        SQL);

        // ⛔ A DOCUMENT WITHOUT A SLUG IS UNREACHABLE BY SKILL 7, AND A SLUG ON
        // ANYTHING ELSE IS A SECOND WAY TO ADDRESS A LINK THAT HAS ONE ALREADY.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_links
                ADD CONSTRAINT tenant_links_slug_belongs_to_documents
                CHECK (
                    (kind = 'document' AND slug IS NOT NULL AND btrim(slug) <> '')
                    OR (kind <> 'document' AND slug IS NULL)
                )
        SQL);

        // ⛔ THE FEE IS THE PAYMENT LINK'S ALONE. `fee_cents` and `fee_currency`
        // stand or fall together — cents with no currency is `18`'s bare integer,
        // the shape that lets one currency be added to another.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_links
                ADD CONSTRAINT tenant_links_fee_belongs_to_payment
                CHECK (
                    (kind = 'payment' OR (fee_cents IS NULL AND fee_currency IS NULL AND fee_covers IS NULL))
                    AND (fee_cents IS NULL) = (fee_currency IS NULL)
                    AND (fee_cents IS NULL OR fee_cents >= 0)
                )
        SQL);

        // ⚠️ **A COVERS LINE WITH NO FEE IS A SENTENCE ABOUT A NUMBER NOBODY
        // SET.** Skill 6 states the fee *and* what it covers; the second half
        // alone would have the assistant explaining the scope of a charge it
        // cannot name.
        DB::statement(<<<'SQL'
            ALTER TABLE tenant_links
                ADD CONSTRAINT tenant_links_covers_needs_a_fee
                CHECK (fee_covers IS NULL OR fee_cents IS NOT NULL)
        SQL);

        // ⛔ ONE BOOKING LINK AND ONE PAYMENT LINK PER BUSINESS. Two would each
        // ground skill 5, and which one the assistant sent would depend on row
        // order — a link a customer follows to somewhere the business forgot.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tenant_links_one_per_single_valued_kind
                ON tenant_links (business_id, kind)
                WHERE kind <> 'document'
        SQL);

        // The slug is what skill 7 addresses a document by, so it is unique
        // within the tenant and deliberately not across the platform: two
        // businesses both calling something "price sheet" is the ordinary case.
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tenant_links_document_slug_is_unique_per_tenant
                ON tenant_links (business_id, slug)
                WHERE kind = 'document'
        SQL);

        DB::statement('ALTER TABLE tenant_links ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE tenant_links FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON tenant_links
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_links');
    }
};
