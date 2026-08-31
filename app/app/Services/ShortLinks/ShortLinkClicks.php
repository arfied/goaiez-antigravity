<?php

declare(strict_types=1);

namespace App\Services\ShortLinks;

use App\Enums\ClickDeviceClass;
use App\Enums\ClickDiscardReason;
use App\Enums\ShortLinkPurpose;
use App\Models\Customer;
use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Record what happened to a link, and answer what a contact actually opened.
 *
 * The only reader and writer of `short_link_clicks`, so that the counted/uncounted
 * distinction is decided in one place. A second writer would be a second idea of
 * what a click is, and the two would disagree on the screen that matters.
 */
final readonly class ShortLinkClicks
{
    /**
     * Store one fetch.
     *
     * ⚠️ **CALLED INSIDE THE TENANT ESTABLISHED FROM THE LINK**, never before —
     * the redirect arrives with no tenant, `ShortLinks::resolve()` is what
     * produces one, and this write is scoped by it. That ordering is the reason
     * `short_link_clicks` needs only `tenant_isolation` while `short_links`
     * needs `public_read` beside it.
     */
    public function record(
        ShortLink $link,
        ?ClickDiscardReason $discardReason,
        ClickDeviceClass $deviceClass,
    ): ShortLinkClick {
        Tenancy::idOrFail();

        return ShortLinkClick::query()->create([
            'short_link_id' => $link->id,
            // Denormalised at write time so the click keeps naming the contact
            // it was attributed to, whatever later happens to the link.
            'customer_id' => $link->customer_id,
            'clicked_at' => now(),
            'counted' => $discardReason === null,
            'discard_reason' => $discardReason,
            'device_class' => $deviceClass,
        ]);
    }

    /**
     * What this contact actually opened, newest first.
     *
     * ⛔ **`counted` ONLY, AND THIS IS THE METHOD THE TIMELINE USES.** An
     * uncounted fetch on a person's timeline is a false sentence about them —
     * *"they opened your message"* about somebody whose carrier scanned it.
     * Decision 113's rule for review destinations, met again one layer down: a
     * click is never evidence of the thing it would suggest, so the one place
     * that turns clicks into a story about a person takes only the ones this
     * application is prepared to defend.
     *
     * @return Collection<int, ShortLinkClick>
     */
    public function countedForCustomer(Customer $customer): Collection
    {
        Tenancy::idOrFail();

        return ShortLinkClick::query()
            ->where('customer_id', $customer->id)
            ->where('counted', true)
            // ⚠️ **`id` RATHER THAN `clicked_at`, AND BOTH REASONS ARE REAL.**
            // Two fetches inside one second have an order and a timestamp cannot
            // express it — a preview fetch and the tap that follows it are
            // exactly that pair. And decision 289's lint requires any other
            // descending sort to say `NULLS LAST` out loud, because Postgres
            // puts NULLs first; `id` is NOT NULL and is the one column exempt
            // from that. Insert order and click order are the same thing here.
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Whether this contact opened a link of this kind after a moment — T176 P14.
     *
     * ⛔ **THIS IS THE "NO CLICK" HALF OF THE FOLLOW-UP TEST AND IT IS NOT A
     * CLAIM THAT THEY REVIEWED.** Decision 113 is unmoved: no destination
     * platform gives us a completion callback, so a click is never recorded or
     * reported as a review. What it is used for here is the opposite and much
     * weaker statement — *this person acted on the message, so do not nudge
     * them again* — which is a courtesy decision rather than an outcome claim.
     *
     * ⛔ **AND IT IS ONLY HALF THE QUESTION T176 §3 ASKS.** The brief is
     * "no click **and** no review". The second half has no answerable form in
     * this schema: `GoogleReviewIngest` writes no `customer_id` — it cannot,
     * because Google does not tell us who the reviewer is in our terms — so a
     * `reviews.customer_id` predicate over an ingested review would match
     * nothing and read like a working check (256). **So the known limitation is
     * stated rather than papered over: a contact who posted publicly on a
     * platform we get no callback from will still be reminded once.** The
     * mitigation is that a click is the commonest evidence of exactly that
     * journey, because every destination hand-off goes through our own redirect.
     *
     * ⚠️ **`counted` ONLY**, through the same predicate `countedForCustomer()`
     * uses and for its reason: an uncounted fetch is a carrier or a mail client
     * scanning the link, and suppressing somebody's reminder on the strength of
     * a scanner is a false sentence about them with a consequence attached.
     *
     * ⚠️ **THE PURPOSE FILTER IS A RELATION AND NOT A DENORMALISED COLUMN.**
     * `short_link_clicks` carries `customer_id` and nothing about why the link
     * existed; `short_links.purpose` is where that lives. `whereRelation()` keeps
     * the tenant scope on both sides — `ShortLink` carries the trait, so its
     * global scope applies to the subquery — which a hand-written join would not.
     */
    public function hasCountedClickSince(
        Customer $customer,
        ShortLinkPurpose $purpose,
        CarbonInterface $since,
    ): bool {
        Tenancy::idOrFail();

        return ShortLinkClick::query()
            ->where('customer_id', $customer->getKey())
            ->where('counted', true)
            ->where('clicked_at', '>=', $since)
            ->whereRelation('shortLink', 'purpose', $purpose)
            ->exists();
    }
}
