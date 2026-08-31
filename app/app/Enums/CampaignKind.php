<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Campaigns\BroadcastPreconditions;

/**
 * What kind of campaign this is — the discriminator decision 3310 needs and
 * that nothing in this schema had.
 *
 * ⛔ **`OutreachPurpose` CANNOT CARRY THIS DISTINCTION AND THAT IS WHY THIS ENUM
 * EXISTS.** Reactivation **is** marketing (2100), so both kinds send with
 * `OutreachPurpose::Marketing` and every consent, register, quiet-hours and
 * arbiter rule applies identically to both. Purpose answers *what legal basis is
 * needed*; this answers *whose brand and whose number the message rides*, which
 * is a different question with a different answer.
 *
 * ⚠️ **AND `CampaignAudience` CANNOT EITHER.** Dormant-vs-manual is how the
 * recipients were chosen; a broadcast can be either, and a reactivation can be
 * either. Overloading it would have made "hand-picked" mean "spends the tenant's
 * own brand", which is two ideas on one column.
 *
 * ⛔ **THE GUARD IS NOT ON THIS ENUM, DELIBERATELY.** What a broadcast requires
 * is three facts about the *tenant* that are only true or false at the moment of
 * a send — an approved 10DLC registration, their own number, a purchased balance
 * — so the answer lives in {@see BroadcastPreconditions} and is asked per
 * recipient. A method here could only ever return a constant, which is a
 * configure-time answer to a send-time question and is precisely what 3310
 * refuses.
 */
enum CampaignKind: string
{
    /**
     * `SL-2`/`REACT-1` — the dormant-customer win-back this engine was built
     * for. Rides the **GOAIEZ** 10DLC brand and the platform number pool, which
     * is 2101's accepted exposure: the tenant carries the legal basis and the
     * platform carries the carrier reputation.
     */
    case Reactivation = 'reactivation';

    /**
     * SMS broadcasting — the tenant's own marketing blast.
     *
     * ⛔ **NEVER ON A GO AI EZ NUMBER** (3310, verbatim: *"they can not use a go
     * ai ez number for this they must have there own"*). This is the containment
     * 2101 asked for arriving from the owner's side: moving the send onto the
     * tenant's own brand and their own number moves the complaint rate onto the
     * party who attested to the list.
     */
    case Broadcast = 'broadcast';

    /**
     * Whether this kind may only be sent on the tenant's own 10DLC brand and
     * their own number, paid for out of purchased credit.
     *
     * A `match` with no `default`, so a third kind is a compile-time
     * conversation rather than one that quietly inherits the permissive answer —
     * and the permissive answer here is *"send it over our brand"*.
     */
    public function requiresTenantOwnSendingIdentity(): bool
    {
        return match ($this) {
            self::Broadcast => true,
            self::Reactivation => false,
        };
    }

    /**
     * How a reply to this kind of campaign is filed — T176 R20, patch P20.
     *
     * ⚠️ **TWO ENUMS FOR WHAT LOOKS LIKE ONE FACT, AND THEY ARE NOT THE SAME
     * FACT.** {@see CampaignReplyOrigin} has a third case this one cannot have —
     * a review invite is not a `campaigns` row at all — so it is the wider
     * vocabulary, and this is the mapping into it, in one place rather than at
     * every call site that holds a `Campaign` and needs an origin.
     *
     * A `match` with no `default`, so a third kind is a compile-time
     * conversation rather than one that quietly inherits somebody else's reply
     * routing — and that routing decides whether a negative reply reaches the
     * recovery flow or ordinary customer service.
     */
    public function replyOrigin(): CampaignReplyOrigin
    {
        return match ($this) {
            self::Reactivation => CampaignReplyOrigin::Reactivation,
            self::Broadcast => CampaignReplyOrigin::Broadcast,
        };
    }
}
