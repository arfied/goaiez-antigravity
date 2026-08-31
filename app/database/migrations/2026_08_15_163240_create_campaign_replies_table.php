<?php

declare(strict_types=1);

use App\Enums\CampaignReplyOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which send an inbound reply is answering — T176 R20, patch P20.
 *
 * ⚠️ **THE LINKAGE IS A ROW BECAUSE THE JOIN THAT PRODUCES IT CANNOT BE
 * RE-RUN.** `CampaignReplyResolver` matches a reply to a send by hashing the
 * tenant's own contacts and comparing against `inbound_messages.value_hash` —
 * an application-layer match no SQL join can express, because the hash is keyed
 * with the application key. Recomputing it on every read would put that scan on
 * the Inbox, the agent turn and the recovery flow rather than once on the
 * webhook. **The row is also what makes the answer stable**: the resolution
 * window is `campaigns.reply_window_hours`, so a reply linked on Tuesday and
 * read on Friday must not become an orphan because the send aged out from under
 * it.
 *
 * ⛔ **IT IS TENANT-OWNED AND `inbound_messages` IS NOT, WHICH IS THE WHOLE
 * REASON IT IS A SECOND TABLE.** That table is deliberately platform-scoped and
 * holds no tenant — its own docblock is explicit that *"a platform-wide table of
 * numbers is a marketing list."* Everything on this row is one business's: which
 * of their campaigns, which of their contacts, which of their sends. So it
 * carries `business_id`, RLS is enabled and forced, and it is the tenant half of
 * a fact whose other half stays platform-side.
 *
 * ⚠️ **NO IDENTIFIER, NO BODY, NO HASH.** The sender's number lives — hashed —
 * on the `inbound_messages` row this points at, and nothing here repeats it. The
 * contact is named by `customer_id`, which is meaningless outside the tenant
 * whose RLS policy already gates it.
 *
 * ## What is deliberately not a foreign key
 *
 * ⛔ **`campaign_id` AND `recipient_id` HAVE NO FK, AND THE REASON IS
 * {@see CampaignReplyOrigin}.** R20 names two sends that live in different
 * tables: a reactivation or broadcast is `campaigns` / `campaign_recipients`, a
 * review invite is `outreach_messages`. One column cannot be constrained to two
 * tables, and splitting it into four nullable columns with four constraints
 * would put the same discriminator in the schema twice. `origin` is what says
 * which table each id points into, exactly as the DTO's own docblock argues, and
 * the CHECK below is what keeps that discriminator closed.
 *
 * ⚠️ **`campaign_id` IS NULLABLE AND THAT IS NOT A CONVENIENCE.**
 * `review_request_campaigns` has had a model, a factory and an isolation test
 * since 2026-07-30 and **nothing in `app/` has ever written a row to it** —
 * CLAUDE.md's 272 shape. `ReviewInviteSender` writes `outreach_messages` with
 * `campaign_id` unset, so a review-invite reply genuinely has no campaign row to
 * name. Refusing to resolve it would have left the *volume* product — invites —
 * with no context at all, which is R20's own case.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_replies', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained()->cascadeOnDelete();

            // ⚠️ **`cascadeOnDelete` CAN NEVER FIRE AND IS STILL THE RIGHT
            // EDGE.** `InboundMessage` refuses `deleting()` outright — it is
            // append-only so that a redelivery stays a no-op — so nothing can
            // delete the parent. Declaring the cascade says what would be true
            // if that ever changed, rather than leaving the question to whoever
            // changes it.
            $table->foreignId('inbound_message_id')->constrained()->cascadeOnDelete();

            // A string cast to App\Enums\CampaignReplyOrigin — never a Postgres
            // enum type (CLAUDE.md; an ConventionsTest lint fails the build on
            // `->enum(`).
            $table->string('origin');

            // The campaign or invite run, in the table `origin` names. Null when
            // the send had none — see the class docblock.
            $table->unsignedBigInteger('campaign_id')->nullable();

            // The send itself: a `campaign_recipients` row, or an
            // `outreach_messages` row. Never null — a reply with no send is not
            // a linkage, and is simply not recorded.
            $table->unsignedBigInteger('recipient_id');

            // ⚠️ **`nullOnDelete`, MATCHING `outreach_messages`.** A contact
            // erased under a data-subject request must not take with them the
            // record that a campaign drew a reply; the aggregate survives the
            // identity, which is the shape crypto-shred takes everywhere else in
            // this schema.
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // The `SendKey` occasion of the original send, verbatim where there
            // is one. ⚠️ The review-invite path has no `SendKey` at all (2975 is
            // still owed), so its occasion is synthesised from the purpose and
            // the row id — which is what the DTO needs it for: telling a second
            // send to the same contact from the first.
            $table->string('occasion');

            // When the send went out, not when the reply arrived. The recovery
            // flow needs the interval, and skill 16 says "the message we sent
            // you on Tuesday" with it.
            $table->timestamp('sent_at');

            $table->timestamp('created_at')->nullable();

            // ⛔ **ONE LINKAGE PER INBOUND MESSAGE, AT THE DATABASE.** The
            // handler already refuses a redelivery upstream — `inbound_messages`
            // has a unique `provider_message_id`, and the carrier's second
            // delivery never reaches this path — so this is the second layer,
            // for the caller that resolves the same message twice by another
            // route.
            //
            // ⚠️ **SCOPED BY `business_id` RATHER THAN GLOBAL, DELIBERATELY.** A
            // bare unique on `inbound_message_id` would refuse a second tenant's
            // insert with a constraint violation naming a row RLS forbids them
            // from seeing — existence leaking through an error message. The
            // mapping is one-to-one by construction anyway: the tenant is
            // derived from our own receiving number, and R8 makes that a
            // function.
            $table->unique(['business_id', 'inbound_message_id']);

            // The contact timeline's read: what has this person replied to. RLS
            // predicates business_id on every query and Postgres does not index
            // a foreign key automatically — the MySQL habit that does not
            // transfer.
            $table->index(['business_id', 'customer_id']);
        });

        $origins = collect(CampaignReplyOrigin::cases())
            ->map(fn (CampaignReplyOrigin $origin): string => "'{$origin->value}'")
            ->implode(', ');

        // ⛔ **THE DISCRIMINATOR IS CLOSED AT THE DATABASE BECAUSE IT DECIDES
        // WHICH TABLE AN ID POINTS INTO.** With no FK to catch it, an unknown
        // origin resolves a plausible wrong row rather than failing — which is
        // the exact failure `CampaignReplyOrigin`'s own docblock is written
        // against.
        DB::statement(<<<SQL
            ALTER TABLE campaign_replies
                ADD CONSTRAINT campaign_replies_origin_is_known
                CHECK (origin IN ({$origins}))
        SQL);

        DB::statement('ALTER TABLE campaign_replies ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE campaign_replies FORCE ROW LEVEL SECURITY');

        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON campaign_replies
                USING      (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
                WITH CHECK (business_id = nullif(current_setting('app.business_id', true), '')::bigint)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_replies');
    }
};
