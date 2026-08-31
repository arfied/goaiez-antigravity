<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reactivation campaign engine's two tables (T137 `SL-2`, lane `L3`).
 *
 * ⚠️ **`review_request_campaigns` ALREADY EXISTS AND THIS IS DELIBERATELY NOT
 * IT.** That table has a model, a factory and a schema-isolation test, and
 * **nothing in `app/` has ever written a row to it** — decision 272's shape, and
 * 1222's correction that the check belongs before the *design* rather than
 * before the query. Overloading it would have meant building on a store whose
 * columns (`channels`, `schedule`, `audience_filter`, `frequency_cap_days`,
 * `requests_sent`) were shaped for review invitations by somebody who never had
 * to make them work, and inheriting whichever of them turned out to mean
 * something different here.
 *
 * ## Why the audience is materialised into rows
 *
 * ⚠️ **A CAMPAIGN'S RECIPIENTS ARE ROWS, NEVER A QUERY RE-RUN EACH BATCH.** The
 * dormancy predicate moves under its own campaign: a contact who replies
 * halfway through stops being dormant, and re-running the query would drop them
 * from the audience *after* deciding to message them — so the run's own record
 * of who it is for would disagree with itself between batches. Rows also make
 * resume mean something: a campaign stopped mid-flight by a pause resumes on the
 * remainder rather than starting again, and "who did this campaign reach" is a
 * question with an answer a year later, which is what a complaint is answered
 * from.
 *
 * ## What is deliberately not here
 *
 * **No `sent_count` or `refused_count` on `campaigns`.** They are `SUM`s over
 * rows that already exist, and a stored total that disagrees with the rows is
 * `CreditLedger`'s hazard in a cheaper costume — both look right on their own.
 *
 * **No per-recipient body.** The composed text is the same template rendered
 * with one name; storing 5,000 near-identical bodies would put a personal name
 * in a second place for no reader. The body that went out is on the
 * `outreach_messages` row the sender writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⚠️ **`S15` — REACTIVATION IS LOCATION-SCOPED.** A multi-location
            // tenant reactivating one branch must not text the other branch's
            // customers, and the sending number is per location. Nullable means
            // "the whole business", which is the right answer for the single-
            // location tenant that is every tenant at soft launch.
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            // What the owner calls it. Never sent to anybody.
            $table->string('name');

            // Strings cast to App\Enums\CampaignAudience / CampaignStatus —
            // never Postgres enum types (CLAUDE.md; an ConventionsTest lint
            // fails the build on `->enum(`).
            $table->string('audience');
            $table->string('status');

            // The tenant's own wording, with {name} and {link} the only
            // substitutions. ⚠️ Not validated here beyond presence: whether it
            // *fits* depends on the business name, the disclosure, the opt-out
            // and the recipient's own name, so the only honest place to answer
            // is ReactComposer, on the finished body, per recipient.
            $table->text('body_template');

            // ⚠️ **ONE CAMPAIGN-LEVEL SHORT LINK, AND `SL-5` REPLACES IT WITH
            // PER-SEND TOKENS.** The short-link redirector is another lane's and
            // does not exist; minting a token here would mean building half of
            // it in the wrong place. What is enforced today is the part the 159
            // budget depends on — the link is already short and is on the one
            // owned host (decision 2116) — which `Campaigns` checks through
            // `ReactComposer`'s own rule rather than a second copy of it.
            $table->string('link')->nullable();

            // The base picture for the MMS overlay, on the configured disk.
            // Null means this campaign is plain SMS.
            $table->string('base_image_path')->nullable();

            // ⛔ **CONFIRM SURVIVES T137'S "GATE NOTHING"** (decision 2106).
            // Flip-gates and CONFIRM are different mechanisms, and *the first
            // send of a new campaign type* is one of CONFIRM's three reserved
            // cases. A campaign cannot leave Draft without both of these.
            //
            // ⛔ **`confirmation_actor`, NOT `confirmed_by`, AND THE NAME IS NOT
            // A PREFERENCE.** `confirmed_by` is a reserved token: the deletion
            // chokepoint lint in `TenancyTest` refuses **any** file in `app/`
            // outside three named ones from containing it, because those are the
            // columns that decide when an account is destroyed without the
            // two-person rule. The lint caught this column on its first run,
            // which is the lint working. Narrowing it so that a campaign could
            // have the prettier name would trade a real destruction guard for a
            // word. **Do not rename this back.**
            $table->timestamp('confirmed_at')->nullable();
            $table->string('confirmation_actor')->nullable();

            // When the runner may begin. Null means "as soon as it is
            // confirmed".
            $table->timestamp('scheduled_for')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();

            // A user identifier or a system actor string, matching every other
            // service's `$actor`. Not a foreign key, on
            // `customer_imports.attested_by`'s precedent: the record has to
            // survive the user row being deleted.
            $table->string('created_by');

            $table->timestamps();

            // RLS predicates business_id on every query and Postgres does not
            // index a foreign key automatically — the MySQL habit that does not
            // transfer, and the omission decisions 314–316 caught on
            // `destination_clicks`.
            $table->index(['business_id', 'status']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_audience_is_known
                CHECK (audience IN ('dormant', 'manual_select'))
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_status_is_known
                CHECK (status IN ('draft', 'scheduled', 'running', 'paused', 'completed', 'cancelled'))
        SQL);

        // A message with nothing in it still costs a segment, still arrives, and
        // still counts against the brand throughput — the recipient just cannot
        // tell what it was for.
        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_template_is_present
                CHECK (btrim(body_template) <> '')
        SQL);

        // ⛔ **THE CONFIRMATION IS BOTH HALVES OR NEITHER, AT THE DATABASE.**
        // A timestamp with no actor is a confirmation nobody made, which is
        // exactly what CONFIRM exists to produce a record of — and the question
        // asked after an incident is *who approved this*, not *was a column
        // set*. Decision 216's second layer: the CHECK catches the repair script
        // that reached neither the service nor the model.
        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_confirmation_is_whole
                CHECK ((confirmed_at IS NULL) = (confirmation_actor IS NULL))
        SQL);

        // ⛔ **AN UNCONFIRMED CAMPAIGN CANNOT BE IN A SENDABLE STATE.** The
        // service refuses it and this refuses it again, because the state column
        // is one `update()` away from anywhere and the cost of getting it wrong
        // is a marketing send over the platform's own 10DLC brand that nobody
        // approved.
        DB::statement(<<<'SQL'
            ALTER TABLE campaigns
                ADD CONSTRAINT campaigns_sendable_states_are_confirmed
                CHECK (status NOT IN ('scheduled', 'running') OR confirmed_at IS NOT NULL)
        SQL);

        DB::statement('ALTER TABLE campaigns ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE campaigns FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON campaigns
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);

        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();

            // ⚠️ **`cascadeOnDelete` IS WRONG HERE AND `restrictOnDelete` WOULD
            // BE WORSE.** `customers` carries a soft `deleted_at` with a
            // seven-day undo (1540), so an ordinary contact deletion does not
            // reach this table at all. What does reach it is a hard delete, and
            // at that point the send record naming a row that no longer exists
            // is not evidence of anything — the person is gone from the book by
            // an owner decision this table has no standing to veto.
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('status');

            // App\Enums\SendRefusalReason, when the status is `refused`. It is
            // the operator's whole explanation and it names a rule, never a
            // person — no identifier, no hash, no list entry.
            $table->string('refusal_reason')->nullable();

            // ⚠️ **THE `SendKey` HEX, WHICH CARRIES NO IDENTIFIER.** `SendKey`
            // hashes the recipient into a sha256 precisely so that this value
            // can reach a row, a log line and a Horizon tag without putting a
            // mobile number in any of them. Stored so a duplicate outcome can be
            // reconciled against the send that really happened.
            $table->string('send_key', 64)->nullable();

            $table->string('provider_message_id')->nullable();

            // The personalised picture rendered for this one contact, on the
            // configured disk. Null on a plain SMS and on a recipient with no
            // usable name.
            $table->string('media_path')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // ⛔ **ONE ROW PER CONTACT PER CAMPAIGN, AT THE DATABASE.** This is
            // the outer half of T137 §3.1's idempotency: `SendKey` stops the
            // same send being *made* twice, and this stops the same person being
            // *enrolled* twice by two overlapping audience resolutions. A
            // double-clicked launch is the ordinary way that happens.
            $table->unique(['campaign_id', 'customer_id']);

            // The runner's own query: outstanding recipients for one campaign,
            // in enrolment order.
            $table->index(['business_id', 'campaign_id', 'status']);

            // The arbiter's query: has this contact had a touch recently.
            $table->index(['business_id', 'customer_id', 'sent_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE campaign_recipients
                ADD CONSTRAINT campaign_recipients_status_is_known
                CHECK (status IN ('pending', 'sent', 'duplicate', 'refused', 'skipped', 'failed'))
        SQL);

        // A reason belongs to a refusal and to nothing else. An operator screen
        // would otherwise print a refusal beside a delivered message —
        // `SendOutcome`'s own invariant, kept at the row it is written to.
        DB::statement(<<<'SQL'
            ALTER TABLE campaign_recipients
                ADD CONSTRAINT campaign_recipients_reason_belongs_to_a_refusal
                CHECK ((refusal_reason IS NOT NULL) = (status = 'refused'))
        SQL);

        // ⛔ **A REFUSED ROW MUST NOT NAME A PROVIDER MESSAGE.** That would be a
        // message the vendor holds and this application believes it never sent
        // — `SendOutcome`'s invariant, and the one that decides whether an
        // incident review can trust these rows at all.
        DB::statement(<<<'SQL'
            ALTER TABLE campaign_recipients
                ADD CONSTRAINT campaign_recipients_refusal_sent_nothing
                CHECK (status <> 'refused' OR (provider_message_id IS NULL AND sent_at IS NULL))
        SQL);

        // A sent row is timed and named. An accepted send that cannot be named
        // can never have its delivery receipt applied and sits queued forever,
        // which is the failure the receipt webhook exists to prevent.
        DB::statement(<<<'SQL'
            ALTER TABLE campaign_recipients
                ADD CONSTRAINT campaign_recipients_sent_rows_are_complete
                CHECK (status <> 'sent' OR (provider_message_id IS NOT NULL AND sent_at IS NOT NULL))
        SQL);

        DB::statement('ALTER TABLE campaign_recipients ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE campaign_recipients FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON campaign_recipients
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
