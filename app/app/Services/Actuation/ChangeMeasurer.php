<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\SearchConsoleClient;
use App\Enums\AutopilotActionType;
use App\Enums\SiteChangeVerdict;
use App\Enums\SiteMeasurementOutcome;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Models\Business;
use App\Models\Location;
use App\Services\ActivityService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gsc\SearchAnalyticsResult;
use App\Services\Tenant\LocationWebsite;
use App\Services\Visibility\ConnectionState;
use App\Services\Visibility\SearchConsoleProperties;
use App\Services\Warehouse\PageTraffic;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * `29` §2 rule 32's second half, end to end: **measure 14–30 days, auto-rollback
 * on regression** — `BUILD-PLAN` §2.11.3 slice H, the row 9 gate.
 *
 * ## The two sources, and what each can honestly say
 *
 * **The pixel mart** answers about the page itself — pageviews on the changed
 * path, through `App\Services\Warehouse\PageTraffic`, which reads
 * `l2_fact_page_daily` and never `l1_events`. ⛔ **On every deployment that
 * exists this returns nothing at all** (4966) — ⚠️ **and 4966's stated reason,
 * *"nothing delivers the bundle to a page"*, stopped being true on 2026-08-18
 * while the sentence it supports did not (8020)**: the bundle is served, the
 * install screen hands out the snippet, and **no real page has ever loaded
 * it**. ⛔ **THIS FILE WAS FOUND BY THE LINT AND NOT BY THE SWEEP** (8021): the
 * claim wraps across two lines here, so every `grep` for it missed this
 * carrier and the flattening pass did not. That is why the lint derives its
 * carriers instead of listing them. Which is why "no pixel" and "no visitors"
 * are different answers here and not a nicety.
 *
 * **Search Console** answers about the whole property, through the read-only
 * client's `dailyMetrics()`. ⛔ **The scope is not widened and no second read is
 * added** (1083, 5480): what that client can see is the site, so the search
 * signal is a site-level signal and {@see ChangeComparison} refuses to let it
 * claim a win.
 *
 * ## The one thing that would have reverted every healthy change
 *
 * ⛔ **GOOGLE HAS NOT FINISHED COUNTING THE LAST FEW DAYS, AND THE MEASURED
 * WINDOW ENDS TODAY.** `dataState: all` returns fresh days and names the first
 * incomplete one; those days are systematically **low**. The measured window is
 * seventeen days, so two unsettled days at the end of it understate the window
 * by about 11.8% — **which is over the 10% threshold on its own**. Left
 * unhandled, this platform would have auto-reverted a healthy page from a
 * customer's website roughly whenever Google's lag was two days, with a green
 * suite and a plausible-looking number in the evidence. So an unsettled window
 * **defers**: nothing is written, and the sweep reads the same rows tomorrow.
 * The windows are anchored on `applied_at`, so tomorrow's answer is the same
 * answer, and 5675's *"`dataState` stays `all`"* is what makes the boundary
 * visible at all.
 *
 * ⚠️ **THE DEFERRAL IS BOUNDED.** After {@see self::SETTLEMENT_GRACE_DAYS} the
 * search signal is dropped rather than waited on, because a change that is never
 * measured is a change that is never reverted — the failure mode this whole
 * slice exists to prevent, reached by being careful.
 *
 * ## What happens on a regression
 *
 * The evidence is written **first**, then the page is put back through
 * {@see SiteChanges::revert()} — the same path slice J's Undo screen takes —
 * with `rolled_back_by = autopilot`, so an owner undo and an auto-revert stay
 * two facts (5524). Then the fix rests on that site
 * ({@see SiteChangeQuarantines}). ⚠️ **A revert that fails does not lose the
 * finding**: the row keeps its verdict and its evidence, the fix is quarantined
 * anyway, the owner gets an action item, and `dueForRevert()` brings it back
 * tomorrow.
 */
final readonly class ChangeMeasurer
{
    /**
     * How long we will wait for Search Console to settle the measured window
     * before measuring without it.
     *
     * ⚠️ **OURS, AND GENEROUS AGAINST A DOCUMENTED LAG OF ROUGHLY TWO DAYS**
     * (`SearchAnalyticsResult`'s own reading of Google's help documentation,
     * which is why no lag figure is hardcoded anywhere in this application). A
     * week is several multiples of it. What it must not be is infinite: a change
     * nobody ever measures is a change nobody ever reverts.
     */
    public const int SETTLEMENT_GRACE_DAYS = 7;

    /**
     * The revert reason used when a retry cannot find the original.
     *
     * ⚠️ **A FALLBACK RATHER THAN A DEFAULT.** The sentence an owner reads is
     * built from the measurement and stored on the quarantine, and the only way
     * to reach here is a quarantine an operator released between the failed
     * revert and the retry. Inventing a percentage would be worse than saying
     * less.
     */
    private const string UNRECORDED_REGRESSION = 'Measured as worse than the fortnight before the change.';

    public function __construct(private readonly DefaultsRegistry $registry,
        private SiteMeasurements $measurements,
        private SiteChangeQuarantines $quarantines,
        private PageTraffic $pageTraffic,
        private SearchConsoleProperties $properties,
        private SearchConsoleClient $searchConsole,
        private ActivityService $activity,
    ) {}

    public function measure(int $changeId, CarbonImmutable $now): SiteMeasurementOutcome
    {
        $subject = $this->measurements->subject($changeId);

        if ($subject === null) {
            return SiteMeasurementOutcome::NotFound;
        }

        $location = Location::query()->find($subject->locationId);

        if (! $location instanceof Location) {
            return SiteMeasurementOutcome::NotFound;
        }

        // ⛔ **THREE REFUSALS BEFORE ANY WINDOW IS READ, AND ALL THREE ARE
        // ALREADY TRUE OF THE SWEEP'S QUERIES** (5962, 5963, 5965). That is not a
        // reason to leave them out: this method is public, takes an id, and is
        // reachable from a job whose id was chosen a sweep ago — 398's lesson
        // stated forwards, where an outer guard makes the inner one unwritten
        // rather than merely unfalsifiable.

        // ⛔ **ONE `site_changes` ROW, ONE MEASURER** (5860, 5962). `28` §4.3's
        // decider judged this fix on p75 LCP, INP, CLS, the conversion rate and
        // the JavaScript error rate three weeks ago. Page visits and Google
        // clicks are the wrong evidence about a `font-display: swap`, and
        // reverting on them is a fifth trigger nobody specified.
        if ($subject->isTheSpeedLayers()) {
            return SiteMeasurementOutcome::JudgedElsewhere;
        }

        // ⛔ **A CHANGE THE OWNER HAS ALREADY TAKEN OFF IS NEVER MEASURED**
        // (5524, 5963). Measuring it and quarantining the automation on the
        // result would report their decision as our self-correction — and the
        // revert it would reach for raises, because `SiteChanges::revert()`
        // refuses a second one to keep `rolled_back_by` truthful.
        if ($subject->isRolledBack) {
            return SiteMeasurementOutcome::NoLongerLive;
        }

        // ⛔ **THE PAGE THIS ROW NAMES IS NOT ON THE WEBSITE THIS LOCATION NAMES**
        // (5819(a), 5965), so the traffic we would read and the page we would
        // write are two different pages. See
        // {@see SiteMeasurements::addressesTheWebsite()}.
        if (! SiteMeasurements::addressesTheWebsite($subject->url, $location->website_url)) {
            $this->noteUnidentifiable($subject, $location);

            return SiteMeasurementOutcome::PageNotIdentifiable;
        }

        // ⛔ **THE RETRY ARM, AND IT MEASURES NOTHING.** A change already judged
        // a regression whose revert did not land needs the adapter asked again,
        // not the marts read again — the verdict is settled and re-deriving it
        // could only ever produce the same answer from the same anchored
        // windows.
        if ($subject->isMeasured) {
            return $subject->verdict === SiteChangeVerdict::Regressed
                ? $this->putItBack($subject, $location, $this->quarantines->reasonFor(
                    $subject->locationId,
                    $subject->changeType,
                ) ?? self::UNRECORDED_REGRESSION)
                : SiteMeasurementOutcome::NotDue;
        }

        if ($now->lessThan($subject->appliedAt->addDays($this->registry->int('sites.measure.window_ends_days')))) {
            return SiteMeasurementOutcome::NotDue;
        }

        $baselineTo = $subject->appliedAt->subDay()->startOfDay();
        $baselineFrom = $baselineTo->subDays($this->registry->int('sites.measure.baseline_days') - 1);

        $measuredFrom = $subject->appliedAt->addDays($this->registry->int('sites.measure.window_starts_days'))->startOfDay();
        $measuredTo = $subject->appliedAt->addDays($this->registry->int('sites.measure.window_ends_days'))->startOfDay();

        $search = $this->search($location, $subject, $measuredFrom, $measuredTo, $baselineFrom, $baselineTo, $now);

        if ($search === null) {
            return SiteMeasurementOutcome::Deferred;
        }

        [$baselineSearch, $measuredSearch] = $search;

        // ⛔ **AND THE PAGE SIGNAL IS WITHHELD ALTOGETHER FOR A TENANT WITH MORE
        // THAN ONE WEBSITE** (5967). See {@see self::pageTrafficIsAttributable()}.
        $attributable = $this->pageTrafficIsAttributable();

        // ⛔ **A CREATED PAGE HAS NO PAGE-LEVEL BASELINE, AND IT IS WITHHELD
        // RATHER THAN COMPUTED** (5804). See {@see self::window()}.
        $baseline = $this->window($subject, $baselineFrom, $baselineTo, $baselineSearch, $subject->createdThePage, $attributable);
        $measured = $this->window($subject, $measuredFrom, $measuredTo, $measuredSearch, false, $attributable);

        $comparison = ChangeComparison::between($baseline, $measured);

        $this->measurements->record($changeId, $baseline, $measured, $comparison->verdict);

        if ($comparison->verdict !== SiteChangeVerdict::Regressed) {
            return SiteMeasurementOutcome::Measured;
        }

        return $this->putItBack($subject, $location, self::reasonFor($comparison, $subject->createdThePage));
    }

    /**
     * Take the change off the site, rest the fix, and tell somebody if we
     * could not.
     */
    private function putItBack(
        MeasurableChange $subject,
        Location $location,
        string $reason,
    ): SiteMeasurementOutcome {
        // ⛔ **THE QUARANTINE IS NOT CONDITIONAL ON THE REVERT LANDING.** What
        // was measured is that this kind of change harmed this site, and that is
        // true whether or not the site would take the page back. Making the rest
        // depend on the revert would leave the automation free to do it again on
        // exactly the site where it failed to undo it once.
        $newlyQuarantined = $this->quarantines->quarantine(
            $subject->locationId,
            $subject->changeType,
            $subject->id,
            ActuationActor::autopilot(),
            $reason,
        );

        $outcome = $this->measurements->revert($subject->id, ActuationActor::autopilot(), $reason);

        if ($outcome !== null && $outcome->ok) {
            return SiteMeasurementOutcome::Reverted;
        }

        // ⚠️ **AN OWNER ACTION ITEM RATHER THAN A SECOND FEED CARD** (5669). A
        // successful revert already files `SiteChangeReverted` through
        // `SiteChanges`, and two cards for one act is what that decision
        // refuses. **This is the arm that files nothing at all otherwise** — the
        // platform decided a change was harmful, could not remove it, and
        // 3791's lesson is that a containment nobody is told about is a product
        // that has quietly stopped working.
        //
        // ⛔ **ONCE, ON THE FIRST FAILURE, AND THE PREDICATE IS THE QUARANTINE
        // BEING NEW** (5746's shape, and the defect this review found in its own
        // first draft). `dueForRevert()` re-attempts every night for as long as
        // the site refuses, so an unconditional item is **a feed card a night,
        // for ever, about one page** — 3791's own warning that an item per run
        // with nothing behind it is 256's vacuity at the scale of a sentence
        // somebody reads. The quarantine is created exactly once per
        // `(location, change_type)`, which makes it the honest "this is new"
        // predicate. ⚠️ **The log line below is unconditional**, because it is
        // for an operator rather than an owner and *"still failing"* is the
        // thing an operator needs to see nightly.
        if (! $newlyQuarantined) {
            $this->noteUnrevertable($subject, $location, $outcome);

            return SiteMeasurementOutcome::RevertFailed;
        }

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $subject->locationId,
            [
                'automation' => 'actuation.measure_site_change',
                'site_change_id' => $subject->id,
                'change_type' => $subject->changeType,
                'url' => $subject->url,
                'detail' => $outcome instanceof AdapterOutcome
                    ? $outcome->detail
                    : 'the change set could not be loaded',
            ],
        );

        $this->noteUnrevertable($subject, $location, $outcome);

        return SiteMeasurementOutcome::RevertFailed;
    }

    /**
     * Can a row in the pixel mart be attributed to the website this change is
     * on at all?
     *
     * ⛔ **`l2_fact_page_daily`'s KEY IS `(business_id, day, page_path)` — NO
     * HOST AND NO LOCATION** (5967). So for a tenant with two websites,
     * `/about` on one and `/about` on the other are one set of rows, and this
     * measurer cannot tell whose visitors it is counting. **5810 proved the
     * `page_path` predicate is load-bearing; this is the finding that it is not
     * sufficient**, and the direction that reads worst is the quiet one: a busy
     * second site holds the total up and masks a real regression on the page we
     * wrote, permanently.
     *
     * ⚠️ **WITHHELD RATHER THAN ESTIMATED, WHICH IS 5804's ANSWER TO THE SAME
     * SHAPE.** A figure we cannot attribute is not a smaller figure; it is not
     * an answer. {@see ChangeComparison} reads `null` as `InsufficientData`, so
     * such a change is judged on the site-level search signal alone — which
     * 5808 already forbids from claiming a win, and which is exactly the
     * position every created page is in.
     *
     * ⚠️ **HOSTS, NOT WHOLE ADDRESSES.** Two locations at
     * `https://acme.test/nashville` and `https://acme.test/franklin` are one
     * website with two sections (1083's franchisor case), and their paths are
     * genuinely different mart keys.
     *
     * ⛔ **THE REAL FIX IS A DIMENSION ON THE MART AND IT IS NOT OURS.** A host
     * on `l2_fact_page_daily` is the warehouse's derivation to change and the
     * pixel's to report; until then this is the honest reading of the table
     * that exists.
     */
    private function pageTrafficIsAttributable(): bool
    {
        /** @var list<string> $websites */
        $websites = Location::query()
            ->whereNotNull('website_url')
            ->pluck('website_url')
            ->all();

        $hosts = [];

        foreach ($websites as $website) {
            $hosts[LocationWebsite::hostOf($website)] = true;
        }

        return count($hosts) <= 1;
    }

    /**
     * The operator's half of a change set whose page we can no longer name.
     *
     * ⚠️ **AN OPERATOR RATHER THAN AN OWNER, AND ONCE PER SWEEP RATHER THAN
     * NEVER.** The row leaves both due sets, so this is the only thing that says
     * so — 1222's rule that a refusal nothing surfaces is indistinguishable from
     * a refusal nobody made. **It is not an owner action item**: what happened is
     * that they replaced their website, which they are entitled to do, and slice
     * J's screen already carries the change with its own state (5844).
     *
     * ⚠️ **NO URL**, on {@see self::noteUnrevertable()}'s rule: a full URL with a
     * query string is where a booking reference ends up.
     */
    private function noteUnidentifiable(MeasurableChange $subject, Location $location): void
    {
        Log::warning('A site change names a page that is not on this location\'s website', [
            'business_id' => Tenancy::idOrFail(),
            'location_id' => $location->id,
            'site_change_id' => $subject->id,
            'change_type' => $subject->changeType,
        ]);
    }

    /**
     * The operator's half of a revert that did not land.
     *
     * ⚠️ **UNCONDITIONAL, WHERE THE OWNER-FACING ITEM IS NOT.** `29` §2.4
     * permits the tenant in log context and nothing about a person; a location
     * id is neither, and the URL is deliberately absent because a full URL with
     * a query string is where a booking reference ends up.
     */
    private function noteUnrevertable(
        MeasurableChange $subject,
        Location $location,
        ?AdapterOutcome $outcome,
    ): void {
        Log::warning('A measured regression could not be reverted', [
            'business_id' => Tenancy::idOrFail(),
            'location_id' => $location->id,
            'site_change_id' => $subject->id,
            'change_type' => $subject->changeType,
            'detail' => $outcome instanceof AdapterOutcome ? $outcome->detail : 'no change set',
        ]);
    }

    /**
     * One window's figures, from whichever sources could see it.
     *
     * @param  array{clicks: int, impressions: int}|null  $search
     */
    private function window(
        MeasurableChange $subject,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?array $search,
        bool $pageDidNotExist,
        bool $attributable,
    ): ChangeMetrics {
        // ⛔ **THE PAGE-LEVEL FIGURES ARE WITHHELD FOR A WINDOW THE PAGE DID NOT
        // EXIST IN, AND A WITHHELD FIGURE IS `null` RATHER THAN `0`** (5804).
        // A page that did not exist did not get zero visits — the question did
        // not apply, and recording a zero would say *"nobody came"* about a
        // page nobody could have come to. Downstream this makes the page signal
        // `InsufficientData` for every creation, deliberately: the only
        // comparable evidence a creation has is the site-level search signal,
        // which {@see ChangeComparison} already refuses to let claim a win.
        if ($pageDidNotExist) {
            return new ChangeMetrics(
                $from,
                $to,
                (int) $from->diffInDays($to) + 1,
                null,
                null,
                $search['clicks'] ?? null,
                $search['impressions'] ?? null,
            );
        }

        // ⛔ **NULL WHEN THIS TENANT HAS NO PIXEL DATA AT ALL, ZERO WHEN NOBODY
        // CAME.** `hasEverMeasured()` is the separator, and on every deployment
        // that exists today it answers false — so this arm is the ordinary one
        // rather than the exotic one (4966).
        //
        // ⛔ **AND NULL AGAIN WHEN THE TENANT HAS MORE THAN ONE WEBSITE**, for a
        // third reason that is neither of those: the mart cannot say which site
        // the visitors were on (5967).
        $traffic = $attributable && $this->pageTraffic->hasEverMeasured()
            ? $this->pageTraffic->forPage($subject->pagePath(), $from, $to)
            : null;

        return new ChangeMetrics(
            $from,
            $to,
            (int) $from->diffInDays($to) + 1,
            $traffic?->pageviews,
            $traffic?->conversions,
            $search['clicks'] ?? null,
            $search['impressions'] ?? null,
        );
    }

    /**
     * Both windows from Search Console, or null when the measured one is not
     * settled yet and this measurement should wait.
     *
     * @return array{array{clicks: int, impressions: int}|null, array{clicks: int, impressions: int}|null}|null
     */
    private function search(
        Location $location,
        MeasurableChange $subject,
        CarbonImmutable $measuredFrom,
        CarbonImmutable $measuredTo,
        CarbonImmutable $baselineFrom,
        CarbonImmutable $baselineTo,
        CarbonImmutable $now,
    ): ?array {
        // ⛔ **NO GRANT IS NEVER A FINDING** (5676, 1084). A tenant who has not
        // connected Google, whose grant was revoked, or whose request failed has
        // no search data at all — and *"we cannot see"* reported as *"nobody
        // searched"* would revert their page on the strength of a connection
        // problem.
        if ($this->properties->connectionState($location) !== ConnectionState::Usable) {
            return [null, null];
        }

        $property = $this->properties->forLocation($location);
        $business = Business::query()->find(Tenancy::idOrFail());

        if ($property === null || ! $business instanceof Business) {
            return [null, null];
        }

        try {
            $measured = $this->searchConsole->dailyMetrics(
                $business,
                (string) $property->site_url,
                $measuredFrom,
                $measuredTo,
            );

            // ⛔ **THE SETTLEMENT CHECK, AND IT IS THE ONLY ONE.** A second
            // filter on each row's `final` flag would be the same condition
            // expressed twice, with the inner one unfalsifiable (398) — the
            // rows' flags are set *from* this boundary by
            // `SearchAnalyticsResult`.
            if ($measured->firstIncompleteDate !== null
                && $measured->firstIncompleteDate->lessThanOrEqualTo($measuredTo)) {
                $waitedUntil = $subject->appliedAt
                    ->addDays($this->registry->int('sites.measure.window_ends_days') + self::SETTLEMENT_GRACE_DAYS);

                if ($now->lessThan($waitedUntil)) {
                    return null;
                }

                // Past the grace: measure on the pixel alone rather than never.
                Log::info('Measuring a site change without Search Console: the window never settled', [
                    'business_id' => (int) $business->id,
                    'location_id' => $location->id,
                    'site_change_id' => $subject->id,
                ]);

                return [null, null];
            }

            $baseline = $this->searchConsole->dailyMetrics(
                $business,
                (string) $property->site_url,
                $baselineFrom,
                $baselineTo,
            );
        } catch (SearchConsoleRequestFailed|ProviderNotConnected) {
            // A failed request is treated exactly as an absent one: Google being
            // down must not revert a page.
            return [null, null];
        }

        return [self::totals($baseline), self::totals($measured)];
    }

    /**
     * @return array{clicks: int, impressions: int}
     */
    private static function totals(SearchAnalyticsResult $result): array
    {
        $clicks = 0;
        $impressions = 0;

        foreach ($result->days as $day) {
            $clicks += $day->clicks;
            $impressions += $day->impressions;
        }

        return ['clicks' => $clicks, 'impressions' => $impressions];
    }

    /**
     * The sentence an owner reads on the feed card, and the one stored on the
     * row.
     *
     * ⚠️ **PLAIN WORDS AND A DENOMINATOR-FREE PERCENTAGE IS NOT ENOUGH** —
     * 3787's rule is that a rate with no denominator beside it is what made two
     * earlier defects hard to see. The window is the denominator here, and it is
     * named. `29` §2 rule 47's outcome language: what happened to their page,
     * never how this system is built.
     */
    private static function reasonFor(ChangeComparison $comparison, bool $createdThePage): string
    {
        $parts = [];

        if ($comparison->pageVerdict === SiteChangeVerdict::Regressed && $comparison->pageBasisPoints !== null) {
            $parts[] = 'visits to the page fell '.intdiv(abs($comparison->pageBasisPoints), 100).'%';
        }

        if ($comparison->searchVerdict === SiteChangeVerdict::Regressed && $comparison->searchBasisPoints !== null) {
            $parts[] = 'clicks from Google to the site fell '.intdiv(abs($comparison->searchBasisPoints), 100).'%';
        }

        // ⚠️ **THE SENTENCE NAMES WHICH KIND OF UNDO THIS IS, BECAUSE THE FEED
        // CARD CANNOT** (5807). `SiteChanges::revert()` files
        // `SiteChangeReverted` — *"Undid a change that was not working"* — for
        // every revert there is, and taking a **new page** down is a different
        // thing happening to somebody's website from putting an edited page
        // back. Naming the case is 5669's pattern and belongs to the caller;
        // what a caller can reach today is this reason string, which is the
        // metadata the card carries. A distinct action type is owed to whoever
        // can widen that method.
        $act = $createdThePage
            ? 'Took the new page back down. Measured over days 14–30 against the fortnight before it went up: '
            : 'Put the page back as it was. Measured over days 14–30 against the fortnight before the change: ';

        return $act.($parts === [] ? 'the site did worse' : implode(' and ', $parts)).'.';
    }
}
