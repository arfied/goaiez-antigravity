<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Enums\CampaignRecipientStatus;
use App\Enums\MarketingTouch;
use App\Enums\OutreachChannel;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\MessageLog;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * `S1` — the send-collision arbiter.
 *
 * Staged 2026-08-08 and named in T137 `SL-2`'s campaign engine: *"one marketing
 * touch per contact per 24h, channel-aware, service-class exempt (missed-call
 * and chat replies + transactional never blocked), priority order invite >
 * recovery > reactivation > campaign"*.
 *
 * ## What problem it actually solves
 *
 * ⚠️ **EVERY GATE BENEATH THIS ONE IS PER-PATH, AND NONE OF THEM CAN SEE EACH
 * OTHER.** `ConsentService` answers whether *this* message may go; the rate
 * governor answers whether *this* number is sending too fast;
 * `ReviewInviteSender`'s own check answers whether *it* has already invited
 * somebody. A review invite, a recovery message and a reactivation campaign can
 * each say yes about the same person on the same afternoon, all three correctly,
 * and the person receives three texts from one business. There is no lower layer
 * where that is visible.
 *
 * ## Why it reads two stores
 *
 * `CustomerTimeline`'s rule, applied one table over: **the union is over the
 * stores that actually have writers**, and a source joins it when it gains one
 * rather than before (decisions 272, 1222).
 *
 *   `outreach_messages`     written by `ReviewInviteSender` today and by the
 *                           send driver when `L2` lands. Read through
 *                           {@see MessageLog::touchesSince()} rather than
 *                           directly, so the chokepoint does not grow an entry.
 *   `campaign_recipients`   written by this lane's runner. It is the only place
 *                           a *campaign* touch is recorded until the driver
 *                           files its own row, and a campaign that could not see
 *                           its own sends would collide with itself.
 *
 * Both are counted, and double counting is harmless: the question is *was there
 * a touch*, not *how many*.
 *
 * ## What it deliberately does not do
 *
 * ⛔ **IT NEVER HOLDS A SERVICE MESSAGE.** The T69 law: a missed-call text-back
 * and a reply in a conversation are never gated, at any hour, by anything.
 * {@see MarketingTouch} has no case for them, so they cannot be passed in — the
 * only way to hold one is to write code that does it deliberately, which is
 * exactly the shape a rule this easy to erode needs.
 *
 * ⛔ **IT IS NOT A CONSENT CHECK AND MUST NEVER BE READ AS ONE.** A `true` here
 * means *nobody else has messaged this person today*. `ConsentService::permit()`
 * is still asked afterwards, and it is what answers suppression, the registers,
 * the state windows and the recorded basis (decision 2099).
 *
 * ⛔ **IT DOES NOT DEFER.** A held contact is refused for this pass and left
 * outstanding; the next pass asks again, and by then the window has usually
 * moved. Building a deferral queue here would be a second scheduler beside the
 * one that already exists.
 */
final class SendCollisionArbiter
{
    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly MessageLog $messages,
    ) {}

    /**
     * Whether a touch of this class may reach this contact on this channel now.
     *
     * ⚠️ **THE PRIORITY LADDER DECIDES WHO GIVES WAY, NOT WHO WINS A RACE.**
     * There is no reservation and no queue: an invite that has already gone
     * blocks a reactivation for the rest of the window, and an invite arriving
     * *after* a reactivation is not retroactively preferred — it is simply
     * blocked too, because the person has had their message. What the ladder
     * buys is that a *lower* class never displaces a *higher* one, which is the
     * only ordering that can be enforced without knowing the future.
     */
    public function allows(Customer $customer, OutreachChannel $channel, MarketingTouch $touch): bool
    {
        return $this->blockingTouch($customer, $channel, $touch) === null;
    }

    /**
     * The touch already inside the window that this one gives way to, if any.
     *
     * Returned rather than a bare boolean so a refusal can be explained: an
     * operator asking why a campaign skipped somebody needs to know it was the
     * review invite that went out this morning, not merely that "the arbiter
     * said no".
     */
    public function blockingTouch(Customer $customer, OutreachChannel $channel, MarketingTouch $touch): ?MarketingTouch
    {
        Tenancy::idOrFail();

        $since = CarbonImmutable::now()->subHours($this->windowHours());

        foreach ($this->touchesSince($customer, $channel, $since) as $existing) {
            if ($touch->yieldsTo($existing)) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Every touch class recorded for this contact inside the window.
     *
     * @return list<MarketingTouch>
     */
    private function touchesSince(Customer $customer, OutreachChannel $channel, CarbonImmutable $since): array
    {
        $touches = $this->messages->touchesSince($customer, $channel, $since);

        // ⚠️ **`sent_at`, NOT `created_at`, ON THIS TABLE — THE OPPOSITE OF THE
        // ONE ABOVE, AND FOR THE SAME REASON.** A `campaign_recipients` row is
        // written when the audience is *resolved*, which can be days before
        // anything is sent, so `created_at` here means "was enrolled" rather
        // than "was messaged". On `outreach_messages` the row is written inside
        // the send transaction, so there `created_at` is the conservative one.
        // The two columns disagree because the two tables mean different things,
        // and picking the same name on both would be wrong on one of them.
        //
        // ⛔ **AND A SEND WHOSE OUTCOME NOBODY COULD ESTABLISH COUNTS TOO, WHICH
        // IT DID NOT UNTIL 7367.** `CampaignRecipientStatus::Unknown` means the
        // message may already be on somebody's handset, and this class was the
        // one reader for which that possibility matters most: without the second
        // arm a **second campaign** could reach the same contact the same day,
        // over a touch this application had decided it could not rule out.
        // 7192(a) named the gap and 7198(a) put it to the owner.
        //
        // ⛔ **IT IS A SECOND PREDICATE AND NOT A WIDER READING OF `sent_at`,
        // WHICH IS THE WHOLE OF WHY 7192(a) REFUSED THE OBVIOUS FIX.** Writing
        // `sent_at` on an `Unknown` row would have made this query work
        // unchanged and would have given one documented column two meanings —
        // *"was messaged"* and *"may have been messaged"* — in a table where
        // three CHECK constraints and `CampaignReplyResolver` all read it as the
        // first. **The column keeps its meaning; the question gets a second
        // clause.** The two facts stay tellable apart everywhere else.
        //
        // ⚠️ **`updated_at` IS THE ATTEMPT TIME FOR THIS SET AND FOR NO OTHER
        // STATUS**, which is why the clause carries the status with it: an
        // `unknown` row is terminal, so its last write *is* the send that could
        // not be confirmed. The `add_send_handle` migration argues it in full.
        $recent = CampaignRecipient::query()
            ->where('customer_id', $customer->getKey())
            ->where(function (Builder $query) use ($since): void {
                $query
                    ->where(fn (Builder $sent): Builder => $sent
                        ->whereNotNull('sent_at')
                        ->where('sent_at', '>=', $since))
                    ->orWhere(fn (Builder $unconfirmed): Builder => $unconfirmed
                        ->where('status', CampaignRecipientStatus::Unknown->value)
                        ->where('updated_at', '>=', $since));
            })
            ->exists();

        if ($recent) {
            // ⚠️ **EVERY CAMPAIGN IS `Reactivation` HERE AND THAT IS AN
            // OVER-STATEMENT THIS LANE OWNS.** `campaigns` has no touch-class
            // column, because REACT-1 is the only campaign type T137 `SL-2`
            // asks for. Classifying them all as the *higher* of the two
            // available classes is the conservative direction: it can only
            // cause an ordinary campaign to give way, never the reverse.
            $touches[] = MarketingTouch::Reactivation;
        }

        return array_values(array_unique($touches, SORT_REGULAR));
    }

    /**
     * The window, in hours, from the registry.
     *
     * ⚠️ **NOT A CONSTANT** — `S14` stages an owner-adjustable knob for it, and
     * `38` Part 2 names "window" among the things that may not be a literal.
     * What is *not* built is the tenant-facing screen: `CLAUDE.md`'s standing
     * rule is that a tenant toggle is a future support ticket and the owner has
     * overruled it exactly once, for the invite threshold. The next toggle needs
     * its own ruling, so this ships as a platform default.
     */
    private function windowHours(): int
    {
        return $this->registry->int('messaging.marketing_touch_window_hours');
    }

    /**
     * The touch class this campaign's sends count as.
     *
     * ⚠️ **ONE ANSWER TODAY, AND IT IS A METHOD SO THAT THE RUNNER AND THE
     * ARBITER CANNOT DISAGREE.** `REACT-1` is the only campaign type T137
     * `SL-2` asks for, so `campaigns` carries no touch-class column and every
     * campaign is a reactivation. A campaign that classified itself one way when
     * blocking others and another way when being blocked would be a campaign
     * that always wins, and the disagreement would live in two files that each
     * looked right.
     *
     * Whoever adds a second campaign type adds the column and changes this
     * method, in one place, with `touchesSince()`'s matching over-statement
     * beside it.
     */
    public function classOf(Campaign $campaign): MarketingTouch
    {
        unset($campaign);

        return MarketingTouch::Reactivation;
    }
}
