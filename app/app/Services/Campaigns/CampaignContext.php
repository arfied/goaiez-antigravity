<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Enums\CampaignReplyOrigin;
use App\Services\Messaging\Outbound\SendKey;
use Illuminate\Support\Carbon;

/**
 * Which send a customer is replying to (T176 R20, patch P20).
 *
 * R20 is an owner clarification of record: *"the marketing text is a review
 * request, and the follow-up conversation happens if they say anything."* A
 * reply to a review invite or a reactivation send enters the agent conversation
 * **carrying this** — which send prompted it, which customer, which campaign —
 * because skill 16 answers a bare "who is this?" completely differently
 * depending on it, and because negative sentiment on a campaign reply routes to
 * the recovery flow rather than to customer service.
 *
 * ⚠️ **IT CARRIES IDS AND AN OCCASION, NEVER A NUMBER OR A BODY.** This value
 * travels into job payloads, Horizon tags and the model prompt. {@see SendKey}
 * already establishes that a mobile number does not go to those places, and the
 * agent prompt is the most exposed of the three — a customer who asks the
 * assistant to repeat its instructions gets whatever is in it.
 *
 * ⛔ **AND IT IS NOT A PERMIT.** Carrying campaign context does not authorise a
 * send: the reply opens a *service-class* conversation (T69, R23), so agent
 * replies in-thread are exempt from quiet hours and the arbiter, while any NEW
 * marketing touch stays arbiter-governed and quiet-hours-gated exactly as
 * before. A caller that reads this object as "we may message them" has inverted
 * the rule — R23's statutory half applies to initiations regardless of what is
 * in this DTO.
 *
 * ⛔ **`$campaignId` IS NULLABLE SINCE P20, AND THE WIDENING IS A FINDING RATHER
 * THAN A CONVENIENCE.** It was `int` when this contract was written, on the
 * premise that a review invite names a `review_request_campaigns` row.
 * **Nothing in `app/` has ever written one** — that table has had a model, a
 * factory and an isolation test since 2026-07-30 and no writer, which is
 * CLAUDE.md's 272 shape and 1222's correction that the check belongs before the
 * *design* rather than before the query. `ReviewInviteSender` writes
 * `outreach_messages` with `campaign_id` unset, so every invite reply — the
 * volume product at soft launch — has no campaign id to carry.
 *
 * The two ways out were widening it or resolving invite replies to `null`, and
 * the second fails **silently**: R20's commonest case would open with no context
 * for ever, on a green suite. Widening fails **loudly** — Larastan reddens every
 * consumer that dereferences it, which is the direction this has to fail.
 * ⚠️ **`$recipientId` stays non-null**, because it is what a caller actually
 * needs in order to find the send.
 */
final readonly class CampaignContext
{
    /**
     * @param  CampaignReplyOrigin  $origin  Which table `$campaignId` points
     *                                       into, and how skill 16 opens — an
     *                                       invite and a reactivation read
     *                                       differently. Required, and named
     *                                       first, because the id below is
     *                                       ambiguous without it.
     * @param  ?int  $campaignId  The campaign or review-invite run that sent it,
     *                            in the table `$origin` names — and null when
     *                            the send names none, which the class docblock
     *                            above argues at length.
     * @param  int  $recipientId  The recipient row. Tenant-owned and RLS-forced,
     *                            so it resolves only inside the tenant this
     *                            context was built in. ⚠️ **Which table it
     *                            points into is `$origin`'s answer too**: a
     *                            `campaign_recipients` row for a reactivation or
     *                            a broadcast, an `outreach_messages` row for a
     *                            review invite.
     * @param  ?int  $customerId  The contact, when the send had one resolved.
     * @param  Carbon  $sentAt  When the send went out — not when the reply
     *                          arrived. Skill 16 says "the message we sent you
     *                          on Tuesday" with it, and the recovery flow needs
     *                          the interval.
     * @param  string  $occasion  The `SendKey` occasion of the original send,
     *                            verbatim. It is what lets a second campaign to
     *                            the same contact be told from the first.
     */
    public function __construct(
        public CampaignReplyOrigin $origin,
        public ?int $campaignId,
        public int $recipientId,
        public ?int $customerId,
        public Carbon $sentAt,
        public string $occasion,
    ) {}

    /**
     * Whether a negative reply on this send routes to recovery rather than to
     * ordinary customer service (R20's *"negative sentiment → the recovery/triage
     * flow"*).
     *
     * ⚠️ **THE ANSWER LIVES ON THE ORIGIN, NOT HERE** — this forwards to
     * {@see CampaignReplyOrigin::routesNegativeReplyToRecovery()} so there is
     * one exhaustive `match` rather than two that can disagree. A caller
     * matching on `$origin` by hand is the thing both are written to prevent.
     */
    public function routesNegativeReplyToRecovery(): bool
    {
        return $this->origin->routesNegativeReplyToRecovery();
    }
}
