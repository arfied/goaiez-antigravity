<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\ReviewSource;
use App\Models\AutopilotSettings;
use App\Models\Location;
use App\Models\Review;
use App\Models\ReviewHubPage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Feedback\FeedbackPages;
use App\Support\SqlState;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The only reader and writer of `review_hub_pages` — the hosted review hub
 * (`29` §7.6, Appendix A's `/r/{slug}`, DATA-MODEL §5.11).
 *
 * ---------------------------------------------------------------------------
 * ⛔ THIS TABLE HAD NO WRITER, NO READER, NO ROUTE AND NO VIEW
 * ---------------------------------------------------------------------------
 * A model, a factory, an RLS policy and a schema isolation test, and nothing in
 * `app/` that could create a row — `CLAUDE.md`'s first recurring failure shape,
 * alongside `Business::provision()`, `autopilot_settings`, `feedback_pages`,
 * `plugins`, `subscriptions` and the rest. `autopilot_settings.update_review_hub`
 * shipped defaulting to **true** for every tenant with nothing anywhere reading
 * it, which is the same shape one column over: a switch that is on, that nobody
 * consults, on a surface that does not exist.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE SLUG IS THE LOCATION'S FEEDBACK-PAGE SLUG, AND THAT IS THE LOAD-BEARING
 * DESIGN DECISION IN THIS FILE
 * ---------------------------------------------------------------------------
 * `/f/{slug}` is where a customer leaves a review; `/r/{slug}` is where anybody
 * reads them. One address per location, two verbs.
 *
 * ⚠️ **The alternative was a second public slug directory, and it was refused on
 * privacy grounds rather than on effort.** `/r/{slug}` is unauthenticated, so
 * *something* has to be readable with no tenant established in order to
 * establish one — `feedback_pages` (decision 318) and `plugins` (400) are the
 * two tables that carry `public_read` for exactly that. Giving
 * `review_hub_pages` the same pair would have made **`title` and
 * `intro_content` — tenant-authored display prose — readable by every
 * unauthenticated request in the system**, which is precisely what the
 * `feedback_pages` migration forbids in as many words: *"IT HOLDS THE MAPPING
 * AND NOTHING ELSE… Resist adding a display column here for convenience."* A
 * table with display columns is the wrong shape for `public_read`, and this one
 * has two.
 *
 * So the mapping stays where the mapping already is. `review_hub_pages` keeps
 * `BelongsToTenant`, keeps its single `tenant_isolation` policy, stays in
 * `V2SchemaIsolationTest`'s dataset unchanged, and is read **after**
 * `ResolveFeedbackPage` has established the tenant — through the ordinary
 * scoped path, with row-level security applying, exactly as that middleware
 * already reads `locations`.
 *
 * ⚠️ **The consequence is that the slug is stored twice**, and that is the cost
 * of the trade rather than an oversight. It is contained by never trusting the
 * copy: {@see publishedFor()} refuses a row whose `slug` does not match the one
 * that resolved, so a divergence 404s instead of serving a page at an address it
 * does not claim. A drift that is checked on every request is not the drift
 * `CLAUDE.md` keeps recording; a drift nobody compares is.
 *
 * ⚠️ **IT IS IMMUTABLE, ON `FeedbackPages`' OWN REASONING, INHERITED WHOLE.** A
 * printed QR sign and a carrier-submitted 10DLC opt-in URL both depend on that
 * address resolving for ever. **Renaming a location changes nothing about this
 * URL** — the slug was minted once, from whatever the location was called at
 * signup, and nothing here re-mints. What *does* follow a rename is the page's
 * visible heading and `<title>`, because those are read live from `locations`
 * on every request and are not stored on this row.
 *
 * ---------------------------------------------------------------------------
 * ⛔ TWO SWITCHES GOVERN WHETHER THIS PAGE IS SERVED, AND THEY ARE NOT THE SAME
 * SWITCH
 * ---------------------------------------------------------------------------
 *   1. `review_hub_pages.is_published` — the page's own state, written `true`
 *      at mint and, like `feedback_pages.is_published`, flipped by nothing in
 *      this codebase today. It is the row saying it exists.
 *   2. `autopilot_settings.update_review_hub` — **the owner's toggle**, one of
 *      the ungated AUTO defaults (`29` §4.5's *"update Review Hub"*), on for
 *      every location since the schema shipped and read by nothing until this
 *      class.
 *
 * ⚠️ **Turning the toggle off takes the page down; it does not delete the row
 * or the reviews**, and turning it back on serves the same address again. That
 * reading was a judgment call and the other one was available: *"update"* could
 * have meant only that automation stops refreshing the page's generated
 * content, leaving it publicly readable. It is read as *take it down* because
 * an owner who switches their review hub off and finds their customers' words
 * still published at a public URL has been surprised in the direction that
 * exposes other people's data — `CLAUDE.md`'s second tiebreaker. Recorded for
 * the owner at decision 6547 rather than guessed silently.
 */
final class ReviewHubPages
{
    /**
     * How many reviews the page renders.
     *
     * The same figure and the same argument as `WidgetReviewController`'s: a
     * hub is a wall, not an archive, and this is an uncached query on a public,
     * unauthenticated endpoint. Fixed rather than caller-controlled — a
     * `?limit=` would let an anonymous request choose how much work we do, and
     * a `?page=` would let it walk the whole table one request at a time.
     */
    public const int MAX_REVIEWS = 20;

    public function __construct(
        private readonly FeedbackPages $feedbackPages,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function maxReviews(): int
    {
        return $this->registry->int('reviews.hub.max_reviews');
    }

    /**
     * Mint this location's hub page, or return the one it already has.
     *
     * IDEMPOTENT UNDER CONCURRENCY rather than merely on repeated calls, and
     * the mechanism is {@see FeedbackPages::provisionFor()}'s line for line:
     * read, then insert inside its own transaction so that a unique violation
     * rolls back only that attempt and leaves the connection able to run the
     * re-select in the catch. Postgres aborts a whole transaction on any error,
     * so catching the violation inside the transaction that caused it would
     * leave nothing able to query.
     *
     * ⚠️ **IT REFUSES A LOCATION WITH NO FEEDBACK PAGE**, which is the one way
     * this differs from the class it copies. The slug is not minted here — it is
     * the feedback page's — so there is nothing to fall back to, and inventing
     * one would create the second address this class exists to avoid.
     * `LocationProvisioner` calls the two in order inside one transaction, so
     * the refusal is unreachable from the only caller; it exists so that a
     * second caller added later fails loudly instead of writing a page at an
     * address that resolves to nothing.
     *
     * @throws InvalidArgumentException when the location belongs to another tenant
     * @throws RuntimeException when the location has no feedback page to take a slug from
     */
    public function provisionFor(Location $location): ReviewHubPage
    {
        $this->assertBelongsToTenant($location);

        $existing = ReviewHubPage::query()
            ->where('location_id', $location->id)
            ->first();

        if ($existing instanceof ReviewHubPage) {
            return $existing;
        }

        $feedbackPage = $this->feedbackPages->forLocation($location);

        if ($feedbackPage === null) {
            throw new RuntimeException(
                'That location has no feedback page, so there is no public address to '
                .'publish its review hub at. LocationProvisioner mints the feedback page '
                .'first for this reason; a caller that reverses the order would publish a '
                .'hub at a slug that resolves to nothing.',
            );
        }

        try {
            return DB::transaction(fn (): ReviewHubPage => ReviewHubPage::query()->create([
                'business_id' => $location->business_id,
                'location_id' => $location->id,
                'slug' => $feedbackPage->slug,
                'is_published' => true,
            ]));
        } catch (QueryException $e) {
            if (SqlState::of($e) !== '23505') {
                throw $e;
            }

            // Lost the race: another caller created this location's page between
            // the read above and this insert. Its row is what provisionFor()
            // promises — the same shape FeedbackPages::provisionFor() documents.
            return ReviewHubPage::query()->where('location_id', $location->id)->firstOrFail();
        }
    }

    /**
     * This tenant's locations that have no hub page, split by whether one can
     * be minted for them.
     *
     * ⛔ **THE BACKFILL'S ONLY READ, AND IT IS HERE BECAUSE OF 6553's
     * CHOKEPOINT RATHER THAN FOR TIDINESS.** `reviews:backfill-hub-pages` may
     * not name `ReviewHubPage` at all — `Architecture/ReviewsTest`'s *"only
     * ReviewHubPages touches the review hub page directory"* fails the build on
     * `ReviewHubPage::`, `new ReviewHubPage`, `->reviewHubPage(` and
     * `table('review_hub_pages')` in every file but this one. So the command
     * asks this question and {@see provisionFor()} answers the write half, and
     * the backfill adds no second way for a row to appear.
     *
     * ⚠️ **BOTH QUERIES ARE ORDINARILY SCOPED AND THAT IS THE POINT.** The
     * command drops the tenant scope on `Business` alone, to walk owners, and
     * establishes a tenant before calling this — so `locations` and
     * `review_hub_pages` are read through the global scope with row-level
     * security `FORCE`d beneath, exactly as a signed-in owner reads them. **A
     * business enumerated by mistake yields the wrong tenant's scope, never
     * another tenant's rows.**
     *
     * ⚠️ **`unaddressable` IS NOT A SYNONYM FOR "BROKEN" AND MUST NOT BE
     * MINTED PAST.** A location with no feedback page has no public address to
     * publish at; {@see provisionFor()} refuses it, and the backfill reports it
     * rather than catching the refusal, because the two are different facts an
     * operator needs told apart. Every location either provisioner has ever
     * made has a feedback page, so a non-empty list here is a finding.
     *
     * ⚠️ **IT SAYS NOTHING ABOUT WHETHER A PAGE WOULD BE *SERVED*.** The
     * owner's `update_review_hub` and the row's `is_published` are asked on
     * every request by {@see publishedFor()}, and the backfill deliberately
     * does not consult either — see decision 6624.
     *
     * @return array{mintable: Collection<int, Location>, unaddressable: Collection<int, Location>}
     */
    public function locationsAwaitingAPage(): array
    {
        $alreadyPaged = ReviewHubPage::query()->pluck('location_id')->all();

        $awaiting = Location::query()
            ->whereNotIn('id', $alreadyPaged)
            ->orderBy('id')
            ->get();

        /** @var list<Location> $mintable */
        $mintable = [];

        /** @var list<Location> $unaddressable */
        $unaddressable = [];

        foreach ($awaiting as $location) {
            if ($this->feedbackPages->forLocation($location) !== null) {
                $mintable[] = $location;

                continue;
            }

            $unaddressable[] = $location;
        }

        return [
            'mintable' => new Collection($mintable),
            'unaddressable' => new Collection($unaddressable),
        ];
    }

    /**
     * The hub page a public request may be served for this location and slug,
     * or null.
     *
     * CALLED WITH THE TENANT ALREADY ESTABLISHED, unlike
     * {@see FeedbackPages::resolve()} — `ResolveFeedbackPage` set it from the
     * slug before this route's controller ran, so this read is ordinarily
     * scoped and row-level security applies to it.
     *
     * ⚠️ **EVERY REASON IT CAN ANSWER NULL IS THE SAME ANSWER**, and the caller
     * turns all of them into one 404: no page minted, page unpublished, the
     * owner's toggle off, a slug that does not match. Distinguishing them would
     * tell a stranger which businesses on this platform have chosen not to
     * publish a hub, which is a fact about a tenant that is none of a
     * stranger's business — the same reasoning `ResolveFeedbackPage` and
     * `MarketingController::liveAudit()` already record.
     *
     * ⚠️ **THE SLUG COMPARISON IS NOT DECORATION.** The slug arrives having
     * resolved against `feedback_pages`; this row holds its own copy. They are
     * written equal and nothing re-mints either, so a mismatch means something
     * has gone wrong that nobody predicted — a hand-repaired row, a restore from
     * an inconsistent backup, a future re-mint path added to one and not the
     * other. Serving the page anyway would publish it at an address it does not
     * itself claim.
     */
    public function publishedFor(Location $location, string $slug): ?ReviewHubPage
    {
        $page = ReviewHubPage::query()
            ->where('location_id', $location->id)
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        if (! $page instanceof ReviewHubPage) {
            return null;
        }

        return $this->hubIsSwitchedOn($location) ? $page : null;
    }

    /**
     * The reviews this page renders, newest first.
     *
     * ⛔ **THIS IS THE FILTERED SET AND IT MUST NEVER BE AVERAGED.** The three
     * conditions are `WidgetReviewController`'s, deliberately, so that the two
     * public surfaces show the same reviews and a change to one is visibly a
     * change to the other:
     *
     *   1. `displayable()` — the moderation gate (decisions 345, 358).
     *   2. `display_on_website` — the per-review switch the owner sets by
     *      approving (decision 403).
     *
     * The widget's third condition, `min_stars_to_show`, is **absent here and
     * that is not an omission**: it lives on `plugins`, it is a per-embed
     * setting for a widget the owner installs on their own site, and there is
     * no hub equivalent — adding one would be a new tenant-facing toggle, which
     * `CLAUDE.md` forbids outright.
     *
     * ⛔ **AND THERE IS A FOURTH CONDITION THE WIDGET DOES NOT HAVE:
     * `source = first_party`. A TEST FOUND THAT ITS ABSENCE WAS A REAL DEFECT
     * RATHER THAN A DIFFERENCE OF OPINION.** `Review::displayable()`
     * deliberately exempts every non-first-party source from the moderation
     * requirement, so a Google row carrying `display_on_website` renders — and
     * {@see TrueRating}'s population is first-party only, on `29` §2 rule 1's
     * two-pipelines rule. Without this clause the page would have shown twenty
     * Google five-stars above a rating of 3.0 computed over four first-party
     * ones, **which is a page whose headline number matches neither pipeline** —
     * exactly the misleading aggregate rule 5 is about, arrived at from the
     * other direction. The list and the population are now the same set of
     * reviews, and the visible copy's claim (*"reviews customers have left …
     * through this page"*) is literally true.
     *
     * ⚠️ **THIS IS NOT RULE 1's PROHIBITION.** Decision 409 already settled it
     * for the widget: not showing a Google review in a business's own marketing
     * surface is not *hiding* it, because rule 1 governs the Google listing and
     * nobody is entitled to appear on somebody's website. It holds more easily
     * here, because this page is on **our** domain rather than the tenant's.
     * Google's reviews stay on Google's listing under Google's own aggregate,
     * which is the number `locations.current_rating` is for and which nothing on
     * this page reads.
     *
     * ⚠️ Whatever the population, {@see TrueRating} does not read any of this,
     * and a lint fails the build if it starts to.
     *
     * @return Collection<int, Review>
     */
    public function displayedFor(Location $location): Collection
    {
        return Review::query()
            ->displayable()
            ->where('location_id', $location->id)
            ->where('source', ReviewSource::FirstParty)
            ->where('display_on_website', true)
            // `id`, never `created_at`: Postgres sorts NULL first on a DESC
            // order by and most tables carry a nullable `created_at`. Slice A's
            // lint fails the build on anything else.
            ->orderByDesc('id')
            ->limit($this->maxReviews())
            ->get();
    }

    /**
     * Whether the owner's `update_review_hub` toggle is on for this location.
     *
     * ⚠️ **A LOCATION WITH NO SETTINGS ROW IS OFF, NOT ON**, even though the
     * column's own default is `true`. `LocationProvisioner` creates the row with
     * the location, so a location without one is a location provisioned by
     * something that did not know all the rows a location is — and the safe
     * reading of "I cannot tell" on a public publication surface is not to
     * publish. It is the same direction `Review::displayable()` fails.
     */
    private function hubIsSwitchedOn(Location $location): bool
    {
        $settings = AutopilotSettings::query()
            ->where('location_id', $location->id)
            ->first();

        return $settings instanceof AutopilotSettings
            && $settings->update_review_hub === true;
    }

    /**
     * Refuse to mint a page for somebody else's location.
     *
     * THE ONLY LAYER THAT CATCHES A MISMATCHED `business_id`/`location_id` PAIR,
     * for the reason {@see FeedbackPages::assertBelongsToTenant()} sets out at
     * length: the `tenant_isolation` policy compares the row's own
     * `business_id` against the session tenant and has no way to reach across to
     * `locations`, so a caller holding a valid session for business A could
     * otherwise write `business_id = A` against business B's location and the
     * database would permit it.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A review hub is published against '
            .'the acting business, so this would publish another business\'s reviews at a '
            .'public address they never asked for.',
        );
    }
}
