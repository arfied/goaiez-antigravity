<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Contracts\Campaigns\CampaignContextResolver;
use App\Enums\AutopilotActionType;
use App\Models\CampaignReply;
use App\Models\Conversation;
use App\Models\InboundMessage;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Sms\InboundMessages;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Record that an inbound message answers one of this tenant's sends — R20's
 * write side, patch P20.
 *
 * ⛔ **THE RESOLVER READS AND THIS WRITES, AND THE SPLIT IS NOT TIDINESS.**
 * {@see CampaignContextResolver} is a day-0 contract three lanes call to *ask a
 * question* — the Inbox rendering a thread, the agent taking a turn, the
 * recovery flow deciding where a complaint goes. A `forInbound()` that wrote a
 * row would make every one of those reads a write, on paths that run inside
 * request handlers and job retries. The one place a linkage is *made* is the
 * carrier webhook, and that is this class.
 *
 * ## Where it sits in {@see InboundMessages}, and what that ordering protects
 *
 * ⛔ **AFTER COMPLIANCE, ALWAYS, AND NOT BECAUSE IT IS LESS IMPORTANT.**
 * STOP, HELP, START and the suppression they write are unconditional under every
 * recorded sending basis (2099) and must not become contingent on a campaign
 * being found. A linkage attempted first would put a tenant lookup, a hash scan
 * and an insert between a person saying STOP and the refusal being written — and
 * `InboundMessages::stop()`'s own docblock is explicit that *"nothing between the
 * caller and `suppressFromCarrier()` may fail."*
 *
 * ⚠️ **AND IT RUNS FOR A STOP TOO.** A STOP is the strongest reply a campaign
 * can draw, and filing it against the send that provoked it is what makes the
 * complaint answerable a year later. Skipping the linkage on the keyword branch
 * would lose exactly the replies worth having.
 *
 * ⚠️ **IT SWALLOWS ITS OWN FAILURE AT THE CALL SITE**, for the reason
 * `countAgainstSender()` gives one method over: an exception escaping a carrier
 * webhook turns a 200 into a 500, and Infobip answers a 500 by redelivering —
 * against a suppression already written and an `inbound_messages` row that will
 * refuse the replay. **The refusal has already landed by the time anything here
 * can throw**, which is what makes swallowing affordable and would not make it
 * affordable one line earlier.
 *
 * ## Idempotency, in two layers
 *
 * A redelivered webhook never reaches this class at all: `InboundMessages`
 * returns early when the unique `provider_message_id` refuses the second insert.
 * The unique index on `(business_id, inbound_message_id)` is the second layer,
 * for a caller that resolves the same message twice by some other route, and a
 * `23505` here is a no-op rather than an error — the linkage it would have
 * written already exists.
 */
final class CampaignReplies
{
    /** Who the record names for an attribution nobody in this company made. */
    public const string ACTOR = 'system:campaign-reply-linkage';

    /**
     * ⚠️ **THE CONCRETE RESOLVER, NOT {@see CampaignContextResolver}, AND THE
     * REASON IS `tenantFor()`.** Writing the row means opening
     * `Tenancy::actingAs()`, which needs the business id the contract's
     * `forInbound()` deliberately does not return — it hands back a context, and
     * a context carries no tenant because it is meant to be read inside one.
     * Re-deriving the tenant here from `to_number` would be a second copy of the
     * one rule that decides whose conversation this is.
     */
    public function __construct(
        private readonly CampaignReplyResolver $resolver,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Link this inbound message to the send it answers, if there is exactly one.
     *
     * Returns the context that was recorded, or **null when this is an ordinary
     * conversation** — which is the common case and not a failure. Most inbound
     * text is a missed-call thread.
     *
     * ⚠️ **THE THREAD IS REQUIRED AND MAY BE NULL, WHICH ARE TWO DIFFERENT
     * THINGS** (4236). Required, because a defaulted argument is how a join key
     * goes missing with no diagnostic — and a linkage nothing can read back is
     * precisely the defect this parameter closes. Nullable, because a STOP
     * threads nowhere and is linked anyway on purpose, and because threading
     * legitimately declines for somebody who is not on the tenant's contact
     * list.
     *
     * @param  ?Conversation  $thread  The thread this reply was filed on, handed
     *                                 down from `InboundThreading::thread()` in
     *                                 this same request. **Never looked up
     *                                 here**: after the fact the only key
     *                                 available is the contact, and a contact
     *                                 has many threads.
     */
    public function link(InboundMessage $message, ?Conversation $thread): ?CampaignContext
    {
        $context = $this->resolver->forInbound($message);

        if ($context === null) {
            // The ordinary answer, and it covers three different facts: the
            // number is the shared Lane A pool number or no longer ours, there
            // was no send in the window, or there were two. All three mean the
            // thread opens without campaign context rather than not opening.
            return null;
        }

        $businessId = $this->resolver->tenantFor($message);

        if ($businessId === null) {
            // ⛔ **UNREACHABLE, AND A THROW RATHER THAN A `return null` FOR
            // EXACTLY THAT REASON.** `forInbound()` answered non-null, which it
            // can only do from inside a tenant it resolved from this same
            // number, so a null here means the two disagree about a number that
            // did not move. ⚠️ **The first draft returned null and a mutation
            // caught it**: the two guards masked each other, so deleting either
            // one left the suite green — 398's shape exactly. An impossible
            // state that answers *"nothing to do"* is indistinguishable from the
            // ordinary answer, so this one says so instead, and the webhook's
            // own `catch` turns it into a logged warning rather than a 500.
            throw new LogicException(
                'A campaign context resolved for inbound message '.$message->getKey()
                .' but its receiving number resolves to no tenant. The resolver answered two '
                .'different questions about one number, which cannot happen unless the number '
                .'moved mid-request.'
            );
        }

        return Tenancy::actingAs($businessId, function () use ($message, $context, $thread, $businessId): CampaignContext {
            $this->record($message, $context, $this->threadToRecord($thread, $context, $businessId));

            return $context;
        });
    }

    /**
     * Which thread id may go on the row — this one, or none.
     *
     * ⛔ **A FOREIGN THREAD IS A THROW, NOT A NULL** (4236). It cannot arise from
     * the one caller — `InboundThreading` opens the thread inside the tenancy
     * this same number resolved — so reaching here means a caller assembled the
     * two halves from different tenants, and writing that id would put one
     * business's campaign context on another business's conversation: the exact
     * cross-tenant render this whole join was designed around. `LogicException`
     * is the precedent one guard above, for its reason: an impossible state that
     * answers *"nothing to do"* is indistinguishable from the ordinary answer.
     * ⚠️ **The webhook's own `catch` turns it into a logged warning rather than
     * a 500**, so failing loudly here costs the linkage and nothing else.
     *
     * ⚠️ **A CONTACT MISMATCH IS A NULL, NOT A THROW, AND THE TWO ARE DIFFERENT
     * KINDS OF EVENT.** Threading matches a contact on the exact normalised
     * number; the resolver matches by hashing each candidate's stored `phone`,
     * and `Identifier::hash()` normalises inside itself — so a contact
     * whose `phone` was stored unnormalised is found by the second and not by
     * the first, and the two can legitimately land on different people. That is
     * a data condition rather than a bug, and the honest response is to record
     * the linkage **without** a thread: the send-to-reply fact is still true,
     * and only the thread it is shown on is unknown.
     */
    private function threadToRecord(?Conversation $thread, CampaignContext $context, int $businessId): ?Conversation
    {
        if ($thread === null) {
            return null;
        }

        if ((int) $thread->business_id !== $businessId) {
            throw new LogicException(
                'Conversation '.$thread->getKey().' belongs to another tenant and was offered as the home '
                .'of a campaign linkage. The thread and the send were resolved in different tenancies, '
                .'which cannot happen unless a caller assembled them by hand.'
            );
        }

        if ($context->customerId === null || (int) $thread->customer_id !== $context->customerId) {
            return null;
        }

        return $thread;
    }

    /**
     * Write the row, the feed entry and the audit line — or find the row is
     * already there and write none of them.
     *
     * ⚠️ **THE UNIQUE INDEX IS THE DEFENCE AND THE `catch` IS HOW WE READ IT**,
     * which is `InboundMessages::record()`'s shape and is used here for the same
     * reason: an `->exists()` check before the insert is passed by both of two
     * concurrent callers, and only the database can refuse the second.
     *
     * ⚠️ **WRAPPED IN A TRANSACTION SO THE VIOLATION ROLLS BACK TO A SAVEPOINT.**
     * A failed statement inside an open transaction aborts the whole transaction
     * in Postgres — every later statement returns `25P02` until it ends — so
     * catching the unique violation without the savepoint would swallow the
     * duplicate correctly and then poison every query after it, including the
     * ones handling the other messages in the same delivery batch.
     */
    private function record(InboundMessage $message, CampaignContext $context, ?Conversation $thread): void
    {
        try {
            $reply = DB::transaction(fn (): CampaignReply => CampaignReply::query()->create([
                'inbound_message_id' => $message->getKey(),
                'origin' => $context->origin,
                'campaign_id' => $context->campaignId,
                'recipient_id' => $context->recipientId,
                'customer_id' => $context->customerId,
                // The join key the Inbox reads back on (4236). Null is an
                // ordinary answer — see `link()` for the three ways a real
                // linkage legitimately has no thread.
                'conversation_id' => $thread?->getKey(),
                'occasion' => $context->occasion,
                'sent_at' => $context->sentAt,
                'created_at' => now(),
            ]));
        } catch (QueryException $e) {
            // 23505 is Postgres' unique_violation, read through `SqlState`
            // rather than `$e->getCode()` — that value is an int on some driver
            // paths and a bare comparison silently misses the match.
            if (SqlState::of($e) === '23505') {
                return;
            }

            throw $e;
        }

        // ⚠️ **IDS AND AN ORIGIN, NEVER A NUMBER, A NAME OR A BODY.** This
        // metadata is broadcast to any open staff screen, and `ActivityService`
        // is explicit that it may never carry customer content. The origin is
        // what makes the entry mean anything to the owner; the ids are what let
        // a screen resolve the rest inside the tenant.
        $this->activity->record(
            AutopilotActionType::CampaignReplyReceived,
            metadata: [
                'origin' => $context->origin->value,
                'campaign_id' => $context->campaignId,
                'recipient_id' => $context->recipientId,
            ],
        );

        // ⚠️ **THE AUDIT LINE IS NOT A DUPLICATE OF THE FEED ENTRY.** The feed
        // is what the owner sees, in their language; this is the append-only
        // record of *which campaign a person's words were filed against*, which
        // is the question a complaint is answered from and the one thing the
        // `campaign_replies` row itself could be argued to have been edited into.
        $this->audit->record(
            action: 'campaign_reply.linked',
            actor: self::ACTOR,
            entity: $reply,
            metadata: [
                'origin' => $context->origin->value,
                'campaign_id' => $context->campaignId,
                'recipient_id' => $context->recipientId,
                'occasion' => $context->occasion,
            ],
        );
    }
}
