<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which kind of send a customer's reply is answering (T176 R20, patch P20).
 *
 * ⚠️ **IT EXISTS BECAUSE THE TWO SENDS R20 NAMES LIVE IN DIFFERENT TABLES.**
 * *"Any reply to it — or to a review invite — enters the agent conversation
 * carrying campaign context."* A reactivation is a `campaigns` /
 * `campaign_recipients` row; a review invite is a `review_request_campaigns`
 * row. {@see CampaignKind} knows only the first pair (`reactivation`,
 * `broadcast`) and cannot name an invite at all, so a context typed on it would
 * silently mis-file every invite reply as a reactivation — the single most
 * common reply at soft launch, since invites are the volume product.
 *
 * ⛔ **SO THE ID IN A CONTEXT IS ONLY MEANINGFUL BESIDE THIS.** Two tables, two
 * id spaces, the same small integers in both. This enum is what says which
 * table `campaignId` points into, and reading one without the other resolves a
 * plausible wrong row rather than failing.
 */
enum CampaignReplyOrigin: string
{
    /** A review invitation — `review_request_campaigns`. */
    case ReviewInvite = 'review_invite';

    /** A reactivation send — `campaigns`, {@see CampaignKind::Reactivation}. */
    case Reactivation = 'reactivation';

    /**
     * A marketing broadcast — `campaigns`, {@see CampaignKind::Broadcast}.
     *
     * ⚠️ **NOT A SOFT-LAUNCH SURFACE, AND PRESENT ANYWAY.** T176 R23 keeps the
     * campaign engine's lowest-priority class dark on the trunk: *"only review
     * requests + reactivation initiate at SL."* The case exists because a
     * broadcast that does go out one day will draw replies, and the alternative
     * to naming it here is an origin the resolver cannot express — which is how
     * a reply gets filed as the wrong thing rather than refused.
     */
    case Broadcast = 'broadcast';

    /**
     * What to call this send on the owner's own screen (4236).
     *
     * ⚠️ **IT NAMES THE MESSAGE, NEVER THE MACHINERY** — `22`'s outcome-language
     * rule. An owner reading their Inbox has not heard of an origin, a
     * reactivation campaign or a `campaign_recipients` row; they sent somebody a
     * text and that person wrote back.
     *
     * ⛔ **AND IT IS NEVER AN ID.** The Inbox renders this and the send date and
     * nothing else about the campaign, because `CampaignContext::$campaignId` is
     * null for every review invite — the volume product — so a surface built on
     * the id would show nothing at all in the commonest case while appearing to
     * work in the rare one.
     *
     * ⚠️ **EXHAUSTIVE `match`, NO `default` ARM** — 3986's rule, for the reason
     * the method below gives.
     */
    public function ownerLabel(): string
    {
        return match ($this) {
            self::ReviewInvite => 'your review invitation',
            self::Reactivation => 'your reactivation message',
            self::Broadcast => 'your announcement',
        };
    }

    /**
     * Whether a negative reply to this send routes to the recovery/triage flow
     * rather than to ordinary customer service.
     *
     * R20: *"negative → recovery/triage routing + owner notify"* — *"a complaint
     * answered well is the recovery feature meeting the campaign feature."*
     *
     * ⚠️ **EXHAUSTIVE `match`, NO `default` ARM** — 3986's rule, and this file
     * would have broken it: a `default` is what lets a future origin inherit an
     * answer nobody chose for it. A new case fails to compile here instead.
     */
    public function routesNegativeReplyToRecovery(): bool
    {
        return match ($this) {
            self::ReviewInvite, self::Reactivation => true,
            // A broadcast reply is a conversation like any other, but it is not
            // a customer telling us about a visit — there is no recovery
            // context to open, so it goes to customer service.
            self::Broadcast => false,
        };
    }
}
