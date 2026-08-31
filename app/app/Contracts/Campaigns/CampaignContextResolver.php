<?php

declare(strict_types=1);

namespace App\Contracts\Campaigns;

use App\Models\Conversation;
use App\Models\InboundMessage;
use App\Services\Campaigns\CampaignContext;
use App\Services\Messaging\Outbound\SendKey;
use App\Support\Identifier;

/**
 * Works out which send an inbound message is answering (T176 R20, patch P20).
 *
 * A day-0 contract: L3 owns the campaign side, L5 the agent side and L6
 * recovery, and all three need this shape before any of them can build. The
 * implementation is P20's.
 *
 * ## The resolution is harder than "match by phone number", and the schema is why
 *
 * ⛔ **`inbound_messages` IS NOT TENANT-OWNED AND HOLDS NO PHONE NUMBER.** It
 * carries `value_hash` — {@see Identifier::hash()} of the sender —
 * and `to_number`, our own receiving number, in clear. Its own docblock is
 * explicit that this is deliberate: *"a platform-wide table of numbers is a
 * marketing list."* So the obvious join, inbound sender against
 * `customers.phone`, is a cross-tenant join on a member of the public's mobile
 * number, which is the exact join decision 3985 refused to write for the STOP
 * trigger and refused for the same reason.
 *
 * ⚠️ **`campaign_recipients.send_key` DOES NOT HELP EITHER.** It is
 * `sha256(business_id | channel | hashed_identifier | occasion)`
 * ({@see SendKey}) — the occasion is inside the
 * hash, so it cannot be recomputed from an inbound message, which knows no
 * occasion. It is an idempotency key, not a lookup key, and using it as one
 * means guessing occasions until a hash matches.
 *
 * ## So resolution goes through our own number, and that has a precondition
 *
 * `to_number` → `phone_numbers.e164` → `business_id` establishes the tenant
 * without touching the sender at all; everything after it happens inside one
 * tenant, under RLS, where `customers.phone` is already unique per business.
 *
 * ⛔ **THAT STEP IS ONLY SOUND UNDER TENANT NUMBER ISOLATION — ONE NUMBER PER TENANT.**
 * `phone_numbers.business_id` is nullable, and at the time of writing the
 * platform sends Lane A over a shared number with no tenant on the row, which is
 * what {@see InboundMessage}'s docblock means by *"no map from a number to a
 * business."* On a shared number this method **must return `null`** rather than
 * pick a tenant: a wrongly-attributed reply hands one business's customer
 * conversation to another business, which is the breach this codebase treats as
 * a Blocker rather than a bug. Tenant number allocation makes the map one-to-one, and P20 is
 * therefore ordered after the number model it depends on.
 */
interface CampaignContextResolver
{
    /**
     * The send this inbound message is answering, or `null` when there is not
     * exactly one.
     *
     * ⚠️ **`null` IS THE ORDINARY ANSWER AND NOT AN ERROR.** Most inbound text is
     * a missed-call thread, not a campaign reply. A caller must treat `null` as
     * *"this is an ordinary conversation"* and never as *"resolution failed"* —
     * the agent still takes the thread (R20 and the T69 law), it simply opens
     * without campaign context.
     *
     * ⛔ **AND IT IS THE ANSWER WHEN ATTRIBUTION IS AMBIGUOUS.** Two open sends
     * to the same contact, or a shared receiving number with no tenant, both
     * resolve to `null`. Guessing the more recent one would be right most of the
     * time, and the times it is wrong are a customer's words filed against
     * someone else's campaign.
     *
     * ⚠️ **IT MUST NOT WIDEN THE TENANT BOUNDARY TO ANSWER.** No
     * `withoutGlobalScope`, no `DB::table`, no join that leaves the tenant
     * resolved from `to_number`. If the question cannot be answered inside one
     * tenant, the answer is `null`.
     */
    public function forInbound(InboundMessage $message): ?CampaignContext;

    /**
     * The send this **thread** began as a reply to, or `null` when no linkage is
     * recorded against it.
     *
     * ⛔ **THIS METHOD EXISTS BECAUSE THE TABLE HAD A WRITER AND NO READER**
     * (4236). `forInbound()` answers about one carrier message, which is what
     * the webhook holds; every screen holds a *thread* instead, and there was no
     * spelling of this question for one — so `ThreadState::isCampaignReply()`
     * returned `false` in production always and R20's *"the reply reads in
     * context rather than as an orphan message"* happened nowhere. CLAUDE.md's
     * decision-272 shape, across three lanes each of which was correct about its
     * own half.
     *
     * ⚠️ **IT IS A LOOKUP AND NEVER A RESOLUTION.** `forInbound()` falls back to
     * matching a reply against open sends; this one may not. A thread carries no
     * `value_hash` to match on, so the only thing it could match on is the
     * contact — and a contact has **many** threads, because
     * `ConversationThreads::openFor()` reuses any unresolved one and nothing in
     * `app/` closes them. Guessing from that set is the error this interface
     * already refuses one method above, with the same consequence — *"a
     * customer's words filed against someone else's campaign"* — except rendered
     * on the tenant's own screen as a fact.
     *
     * ⛔ **`null` IS TWO-VALUED AND A CALLER MAY NOT NARROW IT.** It means *"no
     * linkage is recorded against this thread"*, which covers both *"this is an
     * ordinary conversation"* — the common case — and *"a linkage exists but was
     * written without a thread"*. **Nothing may render it as "not a campaign
     * reply"**: an absent row cannot tell those apart, so an honest surface
     * makes no claim at all rather than a negative one.
     *
     * ⚠️ **AND A NON-NULL ANSWER MAY STILL CARRY A NULL `campaignId`.** A review
     * invite — the volume product — has no `review_request_campaigns` row to
     * name, so a caller that tests `campaignId === null` to decide whether there
     * is context has inverted the check. `CampaignContext::$origin` is what says
     * a context exists and what kind it is.
     *
     * ⚠️ **THE THREAD MUST BELONG TO THE TENANT IN CONTEXT, AND AN
     * IMPLEMENTATION MUST REFUSE A FOREIGN ONE ITSELF.** A caller can hand in a
     * *hydrated* model loaded under another tenant, which neither the global
     * scope nor RLS can see — `AgentThreadStates::refuseForeignThread()`'s
     * precedent.
     *
     * ⛔ **AND AN IMPLEMENTATION MAY NOT CLAIM THAT REFUSAL IS WHAT PROTECTS THE
     * BOUNDARY UNTIL IT HAS DRIVEN IT RED** (4236). It was measured on the one
     * implementation and it is **not** falsifiable behaviourally: with the
     * guard, the explicit `business_id` predicate *and* the global scope all
     * removed, a cross-tenant read still answers null because RLS refuses. That
     * is 398's shape and the rehearsal's own L3 finding, and the honest response
     * is a structural assertion over the emitted query rather than a docblock
     * asserting a layer before it is true (314–316).
     */
    public function forThread(Conversation $conversation): ?CampaignContext;
}
