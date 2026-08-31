<?php

declare(strict_types=1);

namespace App\Services\Destinations;

use App\Enums\AutopilotActionType;
use App\Enums\ClickDiscardReason;
use App\Enums\ReviewDestination;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Models\ReviewDestinationSetting;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The post-submit picker, and what happens when a customer taps one of it
 * (`17` FPR-04 + FPR-04b, slice F).
 *
 * TWO HALVES OF ONE RULE, WHICH IS WHY THEY SHARE A CLASS. offerFor() decides
 * what a customer may be shown; handOff() decides what a customer may be sent
 * to. If those two ever disagree, the disagreement is exploitable: a button that
 * is not rendered but is still honoured means anybody who can guess the URL
 * `/f/{slug}/to/google` gets a click recorded against somebody else's review.
 * Both therefore resolve through the same private eligible() and neither is
 * allowed a shortcut.
 *
 * THIS CLASS DOES NOT DECIDE, IT READS A DECISION. Slice E's ReviewRouter
 * already ran, synchronously, inside the submission transaction, and wrote
 * `routed_destinations` (decision 370). Re-deriving eligibility from thresholds
 * here would be a second implementation of `29` §2 rule 1's gating, and the
 * build-failing gating test only covers the first one. So the routed snapshot is
 * the authority on *which destinations this rating cleared*.
 *
 * ⚠️ AND THE SNAPSHOT IS NOT THE ONLY AUTHORITY. It is a record of what was true
 * at submission; `enabled` is what is true now. An owner who disables Facebook
 * this morning must not have Facebook buttons served from this afternoon's page
 * loads of a review routed last week, so eligible() intersects the snapshot with
 * DestinationSettings::offeredFor(). The intersection fails closed in both
 * directions: dropped from either side means no button and no hand-off.
 *
 * NO URL FROM THE SNAPSHOT, EVER (decision 308). The snapshot deliberately
 * stores no link, so Google's is derived from the location's current place id
 * every time and cannot go stale across a listing merge.
 *
 * TWO THINGS ARE INHERITED FROM THE SNAPSHOT RATHER THAN RE-CHECKED, and both
 * will look like omissions to the next reader:
 *
 *   - **`send_review_requests`.** Decision 381 has ReviewRouter empty the
 *     offered list when an owner switches solicitation off, so an empty column
 *     reaches here and nothing is shown. Re-reading the toggle would be a second
 *     implementation of `29` §2 rule 40's gate, and the two could disagree.
 *   - **Moderation.** A review whose text `AnalyzeReviewJob` later flags still
 *     gets its picker, and that is correct rather than an oversight: flagging
 *     withholds *our* display of their words (decision 345), and nothing about
 *     what somebody wrote on our form removes their right to post on Google.
 *     Withholding the button would be moderating a Google review by the back
 *     door, which `29` §2 rule 1 forbids outright. Routing runs synchronously
 *     inside the submission and analysis is queued, so in practice the picker is
 *     rendered before any verdict exists anyway.
 */
final class ReviewInvites
{
    public function __construct(
        private readonly DestinationSettings $destinations,
        private readonly DestinationClicks $clicks,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
        private readonly TenantSuspension $suspension,
    ) {}

    /**
     * What this customer sees, Google first.
     *
     * Returns an empty list rather than throwing when there is nothing to
     * offer — a 1-star review, a tenant with every destination disabled, or a
     * tenant who switched solicitation off are all ordinary states, and the
     * thanks screen renders a plain thank-you for each of them.
     *
     * @return list<InviteOption>
     */
    public function offerFor(Review $review, Location $location, FeedbackPage $page): array
    {
        $options = [];

        foreach ($this->eligible($review, $location, $page) as $setting) {
            $options[] = new InviteOption(
                destination: $setting->destination,
                // OUR ROUTE, NEVER THE PLATFORM'S. See InviteOption's docblock:
                // the platform URL is resolved on the far side of this redirect
                // so that the host allowlist runs on every hand-off rather than
                // on whoever remembered to re-check a rendered href.
                url: route('feedback.destination', [
                    'slug' => $page->slug,
                    'destination' => $setting->destination->value,
                ]),
            );
        }

        return $options;
    }

    /**
     * Record that somebody was sent to a destination, and say where to.
     *
     * Returns null when this destination is not one this review may be handed
     * off to — a disabled destination, one the rating never cleared, a review
     * from another location, or a link that cannot be derived. **Null means
     * nothing was written**: no click row, no feed item, no audit entry. A
     * caller that cannot get a URL must not report a click, because the customer
     * did not reach the platform.
     *
     * THE CLICK IS THE ONLY THING RECORDED, AND IT IS NOT A REVIEW. Decision 113
     * and `24` §2.3.2: no destination platform gives us a completion callback, so
     * "we sent them" is the whole of what we know. DestinationClicks has nowhere
     * to put an outcome, the feed title says "opened", and the one true signal a
     * review landed is review sync observing it (slice I).
     *
     * NOT IDEMPOTENT, ON PURPOSE. `17` FPR-04b asks for one row per *click*, so a
     * customer who opens Google, comes back and opens it again produces two rows
     * — which is what happened. Collapsing them would be inventing a session
     * concept the spec does not have, and would make the count answer a
     * different question than "how many hand-offs did we make".
     *
     * ⛔ **`$discard` IS "A MACHINE FETCHED THIS", AND IT SUPPRESSES THE ROWS
     * WITHOUT SUPPRESSING THE URL.** Carriers and mail providers fetch every link
     * we send; T137 §5 treats that as a given, and this route is a GET that
     * writes, so a link checker following it mints a `destination_clicks` row and
     * the feed sentence "A customer opened Google to post a review" for somebody
     * who did nothing. That is decision 113's exact prohibition arriving through
     * the back door — the click is not merely miscounted, it is *fabricated*.
     *
     * ⚠️ **THE REASON IS A PARAMETER RATHER THAN THE CALLER SIMPLY NOT CALLING
     * THIS.** A caller that resolved the URL itself would be a second copy of
     * `eligible()`, and this class exists because `offerFor()` and `handOff()`
     * disagreeing is exploitable. Every gate below still runs for a discarded
     * fetch: a scanner guessing `/to/google` on a 1★ review gets null here, the
     * same as a person would.
     *
     * ⚠️ **AND NOTHING RECORDS THE DISCARD, WHICH IS A REAL LOSS.**
     * `short_link_clicks` keeps its discarded fetches with a reason, so a filter
     * that has started eating real people shows up as a sudden mass of one
     * reason. `destination_clicks` has nowhere to put one, deliberately — decision
     * 307's schema test forbids any column that could be read as an outcome, and
     * every row in that table is read as "a customer opened Google". Storing a
     * discarded hand-off would mean a row that must never be counted living in the
     * table whose whole purpose is being counted. So the visibility is bought
     * from the short-link side, where the same classifier runs on the same
     * traffic; see 2925.
     */
    public function handOff(
        Review $review,
        Location $location,
        FeedbackPage $page,
        ReviewDestination $destination,
        ?ClickDiscardReason $discard = null,
    ): ?string {
        $setting = null;

        foreach ($this->eligible($review, $location, $page) as $candidate) {
            if ($candidate->destination === $destination) {
                $setting = $candidate;

                break;
            }
        }

        if (! $setting instanceof ReviewDestinationSetting) {
            return null;
        }

        // eligible() already proved this resolves; calling it again is what
        // returns the live URL rather than the one we rendered a page with.
        $url = $this->destinations->linkFor($location, $setting);

        // ⛔ **THE URL, AND NOT ONE ROW.** A classified fetch is still answered
        // with the redirect on purpose: a link checker handed a 4xx can mark the
        // URL bad and suppress delivery of the whole message, which loses the
        // customer the invite entirely — a far worse failure than a spurious
        // count. The machine goes where a person would have gone; nothing is
        // written about it.
        if ($discard instanceof ClickDiscardReason) {
            return $url;
        }

        // ALL THREE ROWS OR NONE. They are three views of one event — what
        // happened, what the owner sees, and what an investigation reads — and a
        // click with no audit entry is a hand-off `29` §19.3 cannot account for,
        // while a feed item with no click is a sentence backed by nothing. The
        // transaction is also what makes decision 379's ShouldDispatchAfterCommit
        // do its job here: without one, the broadcast leaves before the audit row
        // is even attempted.
        DB::transaction(function () use ($review, $location, $destination): void {
            $this->clicks->record($review, $destination);

            $this->activity->record(
                AutopilotActionType::ReviewDestinationOpened,
                (int) $location->id,
                [
                    'review_id' => (int) $review->id,
                    'destination' => $destination->value,
                ],
                // ⚠️ "OPENED", NEVER "LEFT" OR "POSTED". This is the sentence an
                // owner reads, and it is the one place the click/completion
                // distinction stops being an internal schema property and becomes
                // a claim made to a paying customer. `17` FPR-04b fixes the
                // wording: "invited", "opened Google".
                $destination->openedTitle(),
            );

            $this->audit->record('review.destination_opened', 'customer', $review, [
                'destination' => $destination->value,
                'location_id' => (int) $location->id,
            ]);
        });

        return $url;
    }

    /**
     * The settings this review may actually be handed off to, Google first.
     *
     * Four gates, and each one catches something the others cannot:
     *
     *   1. **Tenant.** Decision 301's shape. A Review hydrated from a session id
     *      is not proof of anything; this class writes a click, a feed item and
     *      an audit row, all filed against the acting business.
     *   2. **Location.** Both are tenant-scoped, so this cannot cross a tenant
     *      boundary — but a multi-location tenant could otherwise offer location
     *      A's Google listing on location B's feedback page, which is a wrong
     *      invite rather than a leak. The page is checked against the location
     *      too, because the slug is what the customer actually presented.
     *   3. **Routed.** The rating cleared this destination's threshold when the
     *      review was submitted (slice E). Skipping this would hand a 1-star
     *      customer a Google button, which is `29` §12.1's gating test failing
     *      through a side door.
     *   4. **Currently enabled and resolvable.** offeredFor() re-reads `enabled`
     *      now, and linkFor() re-runs the host allowlist on every read — decision
     *      314–316's "the read path must fail closed independently of the write
     *      path", which slice E then had to apply to its own consumer (382).
     *   5. **Not suspended.** `28` §9.5's compliance stop, and ⚠️ **this is the
     *      one place a suspension is wider than a pause.** Decision 821 keeps a
     *      *paused* tenant's already-routed buttons live — "the offer is ours to
     *      withhold; the tap is theirs to make" — because the owner chose the
     *      pause and nobody has said the offer was improper. A suspension is
     *      exactly that finding: review gating and fake-review solicitation are
     *      what the control exists for, and the hand-off is the actuation
     *      carrying a third-party platform's terms. Continuing it would be this
     *      application still committing the act it stopped the tenant for.
     *
     *      Read here rather than at the controller so `offerFor()` and
     *      `handOff()` cannot disagree — a button that is not rendered but is
     *      still honoured is what this whole class is arranged to prevent, and a
     *      compliance stop is the worst possible thing to leave on the rendering
     *      side alone.
     *
     * A DESTINATION WHOSE LINK WILL NOT RESOLVE IS DROPPED, NOT RAISED. That is
     * decision 382 stated as behaviour: one missing button beats a 500 on the
     * screen that tells a customer what happened to their feedback. Slice E
     * already records the same condition as `undeliverable_destinations` on the
     * audit row, so it is visible without this page failing.
     *
     * @return list<ReviewDestinationSetting>
     */
    private function eligible(Review $review, Location $location, FeedbackPage $page): array
    {
        if ((int) $review->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That review belongs to another tenant. A hand-off writes a click, a feed '
                .'item and an audit row against the acting business, so this would file one '
                .'business\'s customer traffic under another\'s name.',
            );
        }

        if ((int) $review->location_id !== (int) $location->id
            || (int) $page->location_id !== (int) $location->id) {
            return [];
        }

        // Ahead of the snapshot read, because it is the only gate here that is
        // about the tenant rather than about this review: a suspended account
        // hands nobody off, whatever any individual review was routed to.
        if ($this->suspension->isCurrentTenantSuspended()) {
            return [];
        }

        $routed = $this->routedDestinations($review);

        if ($routed === []) {
            return [];
        }

        $eligible = [];

        foreach ($this->destinations->offeredFor($location) as $setting) {
            /** @var ReviewDestinationSetting $setting */
            if (! in_array($setting->destination->value, $routed, true)) {
                continue;
            }

            try {
                $this->destinations->linkFor($location, $setting);
            } catch (InvalidArgumentException) {
                continue;
            }

            $eligible[] = $setting;
        }

        return $eligible;
    }

    /**
     * The destination keys slice E wrote onto this review.
     *
     * ⚠️ THE COLUMN IS THE FLAT `offered` LIST, NOT THE PAIR ReviewRouter's
     * snapshot() RETURNS. That method computes `['offered' => …, 'undeliverable'
     * => …]` and route() persists only the first half — the second is metadata
     * on the audit row, because it explains an empty list rather than being part
     * of one. Reading `['offered']` off this column finds nothing, silently, and
     * every button disappears with no error anywhere.
     *
     * Defensive about the shape rather than trusting the cast, for the rest of
     * it. `routed_destinations` is JSON: it is null on every review routed
     * before slice E existed and on every Google review (never routed at all,
     * now with a CHECK behind it, decisions 383 and 386), and a repair script or
     * a future migration could put any shape in it. An unreadable snapshot
     * offers nothing, which is the direction to fail.
     *
     * @return list<string>
     */
    private function routedDestinations(Review $review): array
    {
        $snapshot = $review->routed_destinations;

        if (! is_array($snapshot)) {
            return [];
        }

        $keys = [];

        foreach ($snapshot as $entry) {
            if (is_array($entry) && isset($entry['destination']) && is_string($entry['destination'])) {
                $keys[] = $entry['destination'];
            }
        }

        return $keys;
    }
}
