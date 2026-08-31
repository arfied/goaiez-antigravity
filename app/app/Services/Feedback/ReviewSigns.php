<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Enums\AutopilotActionType;
use App\Models\ActivityFeedItem;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Services\ActivityService;
use App\Services\Content\Publishing;
use App\Services\Tenant\LocationProvisioner;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * The printable review sign — the only place one is made.
 *
 * ⛔ **`AutopilotActionType::QrGenerated` HAD A LABEL AND NO WRITER.** It shipped
 * with the action vocabulary, reads *"Made you a review QR code"* in an owner's
 * feed, and nothing in `app/` had ever filed one — 272's shape, and the reason
 * nothing in this product handed a tenant anything they could put on a counter.
 * This class is that writer, and it is deliberately the *only* one: a second
 * caller of `ActivityService::record(QrGenerated)` is a second definition of
 * what "we made you a code" means.
 *
 * ---------------------------------------------------------------------------
 * WHY A PRINTED SIGN IS SAFE AT ALL, WHICH IS A FACT ABOUT THE SLUG
 * ---------------------------------------------------------------------------
 * ⛔ **THE ADDRESS ON THE CARD MUST RESOLVE FOR AS LONG AS THE CARD EXISTS, AND
 * THAT IS ALREADY GUARANTEED.** {@see FeedbackPages} makes the slug immutable
 * once minted — its class docblock names *"a printed QR sign"* as one of the two
 * reasons — so a business renamed to Joe's Dental keeps the slug it was given at
 * signup and the sign on its counter keeps working. **Nothing here re-mints, and
 * nothing here may be given a re-mint path**: that would turn every sign already
 * printed into a dead end, silently, with the suite green.
 *
 * ⚠️ **AND THE SIGN POINTS AT THE FEEDBACK PAGE, NEVER AT A REVIEW PLATFORM.**
 * `24` §2.3 and decision 1161: which destination a customer is offered is
 * decided *after* they rate, from that location's own confirmed listings. A code
 * printed straight to Google would make that decision at the printer, months in
 * advance, for every customer at once — and would be soliciting on a platform
 * whose own guidelines the tenant has not been walked through.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT DOES NOT DO
 * ---------------------------------------------------------------------------
 * ⚠️ **IT MINTS NOTHING.** A location with no `feedback_pages` row gets `null`
 * rather than a freshly provisioned page: minting is
 * {@see FeedbackPages::provisionFor()}'s, it happens inside the same
 * transaction as the `locations` insert, and it takes a `$nameIsPersonal` flag
 * that only {@see LocationProvisioner}'s caller can answer
 * (decision 334). Guessing it here would risk putting a person's own name into
 * a permanent public URL — printed, this time.
 *
 * ⚠️ **AND THAT NULL IS RARER THAN IT LOOKS, WHICH IS WORTH KNOWING BEFORE
 * DESIGNING AGAINST IT.** `LocationProvisioner` is the only thing in `app/` that
 * creates a location and it always mints the page, so the branch is reached by a
 * `Location::factory()` row and by locations provisioned before
 * `feedback_pages` existed — for which that class says in terms that there is no
 * backfill. It is legacy and defensive rather than routine.
 */
final class ReviewSigns
{
    public function __construct(
        private readonly FeedbackPages $pages,
        private readonly QrCodeSvg $qr,
        private readonly ActivityService $activity,
    ) {}

    /**
     * This location's sign, or `null` when it has no feedback page yet.
     *
     * ⚠️ **BUILDING IT IS WHAT RECORDS IT, AND THAT IS ONE CHOKEPOINT RATHER
     * THAN TWO.** A `make()` that writes and a `peek()` that does not would be
     * two answers to "has this tenant been given a code", and the caller would
     * pick the wrong one on the render path. {@see self::recordOnce()} makes
     * repetition free, so every caller can simply ask.
     */
    public function for(Location $location): ?ReviewSign
    {
        $this->assertBelongsToTenant($location);

        $page = $this->pages->forLocation($location);

        if (! $page instanceof FeedbackPage) {
            return null;
        }

        $url = route('feedback.show', ['slug' => $page->slug]);

        $sign = new ReviewSign(
            locationName: (string) $location->name,
            url: $url,
            displayUrl: (string) preg_replace('#^https?://#i', '', $url),
            // The accessible name says what the picture does. It deliberately
            // does not recite the URL: that is printed as text beside the code,
            // where a screen reader can reach it without spelling out a slug.
            svg: $this->qr->render($url, 'Scan to leave feedback for '.$location->name),
        );

        $this->recordOnce($location);

        return $sign;
    }

    /**
     * File the feed entry the first time this location's code is made, and
     * never again.
     *
     * ⚠️ **ONCE PER LOCATION FOR EVER, NOT ONCE PER PRESS.** The code is a
     * function of a slug that cannot change, so a second press produces a byte-
     * identical image — and an owner scrolling their history should not read
     * *"Made you a review QR code"* four times for four reprints of one card.
     * {@see Publishing}'s `$alreadyTold` is the same read,
     * against the same table, for the same reason.
     *
     * ⚠️ **A READ-THEN-WRITE, AND THE RACE IT LEAVES IS BENIGN AND SAID OUT
     * LOUD.** Two presses in the same millisecond would file two identical feed
     * lines. The alternative is a partial unique index on the append-only feed
     * table, which would make every future writer of any action type share a
     * constraint invented for this one — a poor trade against a duplicate
     * sentence in a history, from a button a human presses.
     */
    private function recordOnce(Location $location): void
    {
        $already = ActivityFeedItem::query()
            ->where('action_type', AutopilotActionType::QrGenerated->value)
            ->where('location_id', $location->id)
            ->exists();

        if ($already) {
            return;
        }

        // ⛔ THROUGH `ActivityService` AND NEVER `ActivityFeedItem::create()`,
        // WHICH IS NOW A LINT RATHER THAN A CONVENTION (6640–6656). The read
        // above is a read — the same `$alreadyTold` shape `Content\Publishing`,
        // `Content\AuthorByline`, `Actuation\SiteChanges` and `AnalyzeReviewJob`
        // already use against this table — and the write is the service's.
        //
        // ⚠️ NO TITLE ARGUMENT, DELIBERATELY. `AutopilotActionType::QrGenerated`
        // already reads *"Made you a review QR code"*, which is the whole
        // sentence an owner needs; a custom title would be dropped at runtime
        // unless the case were added to the override allowlist, and would owe an
        // entry in that lint's provenance enumeration. The closed vocabulary is
        // the right answer here rather than the cheap one.
        //
        // No metadata bag either. Nothing reads one for this action, and the
        // slug is a public address that has no business being copied into a
        // second table whose retention is the feed's rather than the page's.
        $this->activity->record(AutopilotActionType::QrGenerated, (int) $location->id);
    }

    /**
     * Refuse to make a sign for somebody else's location.
     *
     * ⛔ **THE INNER GUARD, AND IT IS LOAD-BEARING RATHER THAN BELT-AND-BRACES.**
     * {@see FeedbackPages::forLocation()} is tenant-scoped *by its caller* — the
     * model carries no global scope, because it is what establishes a tenant
     * from a public slug (318, 401) — so handing it another tenant's location
     * returns that tenant's page, and this class would print their address on
     * our tenant's card. `Location` itself is scoped and RLS-FORCEd, so in
     * practice a caller cannot usually load one; 398's rule is that an inner
     * guard must not depend on an outer one, and the outer one here is a query
     * this method never runs.
     *
     * The same comparison and the same reasoning as
     * {@see FeedbackPages::assertBelongsToTenant()}, said in this class's own
     * terms because what goes wrong is different: there, a public address is
     * published into a business that never asked for one; here, one business's
     * feedback page is printed onto another's counter card.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A review sign carries a public '
            .'address a customer scans, so making one for a location the acting business '
            .'does not own would print somebody else\'s feedback page onto their counter.',
        );
    }
}
