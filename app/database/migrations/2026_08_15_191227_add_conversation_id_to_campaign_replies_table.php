<?php

declare(strict_types=1);

use App\Contracts\Campaigns\CampaignContextResolver;
use App\Services\Conversations\InboundThreading;
use App\Support\Identifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which thread a campaign linkage belongs to — the join P20 and P18 each left to
 * the other (4236).
 *
 * `campaign_replies` was written by the carrier webhook and read back by nobody
 * on any screen: `ThreadState::isCampaignReply()` returned `false` in production
 * always, so R20's stated purpose — *"so the reply reads in context rather than
 * as an orphan message"* — happened nowhere. **A table with a writer and no
 * reader, with three green suites**, which is CLAUDE.md's decision-272 shape.
 *
 * ⛔ **`business_id + customer_id` IS NOT A JOIN KEY, AND THIS COLUMN EXISTS
 * BECAUSE IT IS NOT.** The index the linkage already carries is
 * `(business_id, customer_id)`, and reaching a thread through it would be wrong
 * in a way that is invisible on the screen it is wrong on:
 * `ConversationThreads::openFor()` reuses any thread with `resolved_at IS NULL`
 * and **nothing in `app/` ever sets `resolved_at`**, so a contact accumulates
 * threads over time and every one of them would inherit every campaign reply
 * that contact has ever sent. A person who answered a broadcast in January and
 * texted about a delivery in March would have March's thread render *"replying
 * to the message you sent in January"* — one thread showing another thread's
 * campaign, stated on the tenant's own screen as a fact.
 *
 * ⚠️ **AND THE OTHER DIRECTION FAILS TOO**: one contact can hold **several**
 * linkages, so `customer_id` selects a set rather than a row, and picking from
 * it needs a rule — nearest timestamp, most recent — which is the guessing
 * {@see CampaignContextResolver} refuses by name for
 * exactly this hazard: *"the times it is wrong are a customer's words filed
 * against someone else's campaign."*
 *
 * ✅ **THE THREAD IS KNOWN AT WRITE TIME, WHICH IS WHY THIS IS A COLUMN AND NOT
 * A LOOKUP.** `InboundMessages::handle()` threads the message and *then* links
 * it — an ordering that was already there, argued on compliance grounds, and it
 * means the conversation exists before the linkage row is inserted. Recording it
 * is one assignment; inferring it afterwards is a rule nobody can make correct.
 *
 * ## Nullable, and the three ways it is legitimately null
 *
 * ⚠️ **NULL IS NOT A DEGRADED ROW.** A linkage with no thread is still a true
 * and useful fact — it is what answers *"which message provoked this
 * complaint"* a year later.
 *
 * 1. **A STOP, HELP or START reply.** Those never thread —
 *    {@see InboundThreading} refuses them so that no
 *    owner answers a withdrawal of consent conversationally — and they are
 *    linked anyway, deliberately: *"a STOP is the strongest reply a campaign can
 *    draw."*
 * 2. **No contact on the tenant's list**, or a contact whose stored `phone` is
 *    not in normalised form. Threading matches on the exact normalised string;
 *    the resolver matches by hashing each candidate's phone, which normalises
 *    inside {@see Identifier::hash()}. The second can therefore
 *    match where the first does not.
 * 3. **Threading failed and was swallowed**, which that class does on purpose so
 *    that a carrier webhook cannot 500.
 *
 * ⛔ **`nullOnDelete`, NOT `cascadeOnDelete`.** Deleting a thread must not delete
 * the record that a campaign drew a reply — the aggregate outliving the identity
 * is the shape crypto-shred takes everywhere else in this schema, and it is the
 * same call the `customer_id` edge on this table already made.
 *
 * ## No new policy, and that is not an omission
 *
 * The table already carries `ENABLE` + `FORCE ROW LEVEL SECURITY` and a
 * `tenant_isolation` policy from the migration that created it, both predicated
 * on `business_id`. A column is added inside that policy's reach; a second
 * policy would be a second predicate on the same table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_replies', function (Blueprint $table): void {
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();

            // The Inbox's read: which send is this thread answering. RLS
            // predicates `business_id` on every query and Postgres does not
            // index a foreign key automatically — the MySQL habit that does not
            // transfer, and the creating migration says so about its own index.
            $table->index(['business_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('campaign_replies', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'conversation_id']);
            $table->dropConstrainedForeignId('conversation_id');
        });
    }
};
