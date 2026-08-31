<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Enums\AutopilotActionType;
use App\Enums\ReviewDestination;
use App\Models\GoogleRatingSnapshot;
use App\Models\Location;
use App\Models\MonitoringAlert;
use App\Services\ActivityService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Destinations\DestinationSettings;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Review-loss and pause detection over one location's own Google numbers
 * (`GOAIEZ-MASTER-PLAN.md:8293`, `:8385`; wave 38 lane D).
 *
 * ⛔ **THE ONLY CALLER IS `CompetitorSignals::recordOurOwnRating()`, AND THAT IS
 * DELIBERATE.** That method is the only place this platform learns a fresh
 * Google review count for a tenant's own listing, once a day, fanned out by
 * the already-scheduled `visibility:sync-competitors`. Riding inside it means
 * this needs no automation key, no kill switch and no schedule line of its
 * own — it inherits `visibility.competitor_signals`' kill switch, and a tenant
 * whose sync is off is read by neither.
 *
 * ## What "per destination" actually means here
 *
 * The plan's own words are "daily count **per destination**". Google is the
 * only destination this platform has any count substrate for at all —
 * {@see GoogleRatingSnapshot}'s own docblock records why. This class detects
 * nothing about Facebook, Trustpilot, Yelp or BBB, and says so rather than
 * quietly answering only for Google under a name that promises more.
 *
 * ## Two signals, two different risk postures
 *
 * **A drop** ({@see self::evaluateDrop()}) is a single comparison between two
 * known readings and costs nothing to report wrong — an activity-feed
 * sentence an owner can shrug off. **A pause** ({@see self::evaluatePause()})
 * ends by default in an alert only; it does NOT call
 * {@see DestinationSettings::disable()} unless
 * `review_loss.auto_disable_on_pause` is explicitly turned on, because that
 * call is a real, first-of-its-kind action: {@see DestinationSettings} has no
 * caller anywhere in `app/` that ever calls `disable()`, and no screen in this
 * product lets a tenant or a staff member turn a destination back on by hand.
 * An automatic mutation with no self-service undo, fired from a heuristic over
 * a number Google owns, is exactly the shape `CLAUDE.md`'s "less support
 * surface" tiebreaker argues against — so the mechanism is built, wired, and
 * OFF until the owner has watched it fire a few times and decides the signal
 * is worth the risk. See the decision row for the number.
 *
 * ## Absent is not zero, applied to this table too
 *
 * {@see GoogleRatingSnapshot::$review_count} is nullable, and every query here
 * filters `whereNotNull('review_count')` before comparing anything. A read
 * that corroborated nothing is invisible to both signals — never a zero, never
 * "no change".
 */
final class ReviewLossDetection
{
    /**
     * A drop is material at this many reviews, whatever the percentage says —
     * the floor half of the combined rule.
     *
     * ⚠️ **THE ACCEPTED FALSE POSITIVE**: a reviewer deleting their own review,
     * or a small Google spam sweep, can cross this floor with nothing adverse
     * having happened to the tenant. The floor is chosen high enough that a
     * single self-deletion (1) or two (2) does not fire, and low enough that a
     * real loss at a small business — the population with the fewest reviews
     * to lose and the least ability to absorb one wrongly attributed — still
     * does.
     */
    public const string MIN_ABSOLUTE_DROP_KEY = 'review_loss.min_absolute_drop';

    /**
     * A drop is material at this percentage of the prior count, whatever the
     * floor says — the relative half of the combined rule, so a business with
     * hundreds of reviews needs a proportionally larger drop before this fires
     * on routine churn.
     */
    public const string MIN_RELATIVE_DROP_PERCENT_KEY = 'review_loss.min_relative_drop_percent';

    /**
     * The two readings being compared may be at most this many days apart.
     *
     * ⚠️ **WHY THIS EXISTS AT ALL**: comparing across a silent gap — the sync
     * failed, the budget was spent, the queue stalled — is not "day over day",
     * it is "however much the count moved across however many unread days",
     * which can cross the drop threshold on ordinary attrition accumulated
     * over weeks. A gap wider than this is skipped rather than compared.
     */
    public const string MAX_COMPARISON_GAP_DAYS_KEY = 'review_loss.max_comparison_gap_days';

    /**
     * Pause evaluation only runs once a location has at least this many known
     * reviews.
     *
     * ⚠️ **THE SINGLE LARGEST FALSE-POSITIVE POPULATION THIS REMOVES**: a new
     * or very small listing is flat for the most ordinary reason there is —
     * nobody has reviewed it yet — and would otherwise dominate every pause
     * alert this platform ever raised. The plan's own framing is "flagged
     * profiles", which are established listings, not day-one signups.
     */
    public const string PAUSE_MIN_REVIEW_COUNT_KEY = 'review_loss.pause_min_review_count';

    /**
     * How many calendar days a review count must have read identically,
     * across only known (non-null) readings, before this is treated as a
     * pause candidate rather than an ordinary quiet stretch.
     *
     * ⚠️ **THE ACCEPTED FALSE POSITIVE**: a genuinely quiet business — no
     * reviews for a season — reads identically to a flagged, paused profile
     * from this platform's own substrate, because neither produces a moving
     * number. The only lever available to tell them apart is time, and this
     * is deliberately long: {@see self::PAUSE_MIN_REVIEW_COUNT_KEY}'s floor
     * argues an established listing should not usually go this long with
     * literally nothing, but "usually" is not "never", and this will still be
     * wrong for some real, slow, honest businesses. That is the trade the
     * default-off action above exists to make survivable.
     */
    public const string PAUSE_FLAT_DAYS_KEY = 'review_loss.pause_flat_days';

    /**
     * The flat run must be corroborated by at least this many distinct known
     * readings, not merely span this many calendar days.
     *
     * ⚠️ Guards a thin population spanning a wide calendar range from reading
     * as a well-observed pause — two known reads eleven weeks apart, with
     * every read between them absent, span the day count above and prove
     * nothing.
     */
    public const string PAUSE_MIN_FLAT_READS_KEY = 'review_loss.pause_min_flat_reads';

    /**
     * Whether a detected pause may actually disable the Google destination.
     *
     * ⚠️ **SEEDED `false`.** See this class's own docblock — the mechanism is
     * built and correctly wired, and turning it on is the owner's call once
     * the alert has been watched fire for real. While it is `false`, a pause
     * is still recorded and still narrated on the feed; only the
     * {@see DestinationSettings::disable()} call and its recovery half are
     * skipped.
     */
    public const string AUTO_DISABLE_ON_PAUSE_KEY = 'review_loss.auto_disable_on_pause';

    private const string PAUSE_ALERT_TYPE = 'review_loss.pause';

    private const string ACTOR = 'system:review-loss-detection';

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly DestinationSettings $destinations,
        private readonly ActivityService $activity,
    ) {}

    /**
     * Append one day's Google reading and evaluate both signals against it.
     *
     * Called once per day, per location, from
     * {@see CompetitorSignals::recordOurOwnRating()} — never called with a
     * null rating, because that method has already returned before reaching
     * this one when Google sent no rating to record.
     */
    public function record(Location $location, float $rating, ?int $reviewCount): void
    {
        Tenancy::idOrFail();

        // Read the prior known reading BEFORE writing today's row, or today's
        // own row would answer as its own "previous".
        $previous = $reviewCount === null ? null : $this->latestKnown($location);

        GoogleRatingSnapshot::query()->create([
            'location_id' => $location->id,
            'review_count' => $reviewCount,
            'rating' => $rating,
            'captured_at' => CarbonImmutable::now(),
        ]);

        if ($reviewCount === null) {
            // Nothing corroborated today. Absent is not zero: no comparison,
            // no pause re-evaluation, and today's row will simply be skipped
            // by every future query here too.
            return;
        }

        $this->evaluateDrop($location, $previous, $reviewCount);
        $this->evaluatePause($location, $reviewCount);
    }

    private function latestKnown(Location $location): ?GoogleRatingSnapshot
    {
        return GoogleRatingSnapshot::query()
            ->where('location_id', $location->id)
            ->whereNotNull('review_count')
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A single day-over-day comparison. Fires a point-in-time activity item
     * and a resolved `monitoring_alerts` row — a drop is a fact about one
     * transition, not an ongoing condition, so nothing here stays open.
     */
    private function evaluateDrop(Location $location, ?GoogleRatingSnapshot $previous, int $today): void
    {
        if ($previous === null || $previous->review_count === null) {
            return;
        }

        $priorCount = (int) $previous->review_count;

        $gapDays = $previous->captured_at->diffInDays(CarbonImmutable::now());

        if ($gapDays > $this->registry->int(self::MAX_COMPARISON_GAP_DAYS_KEY)) {
            // Too stale a comparison to be "day over day" — skipped rather
            // than reported, on this class's own rule against comparing
            // across a silent gap.
            return;
        }

        $drop = $priorCount - $today;

        if ($drop <= 0) {
            return;
        }

        $minAbsolute = $this->registry->int(self::MIN_ABSOLUTE_DROP_KEY);
        $minRelativePercent = $this->registry->int(self::MIN_RELATIVE_DROP_PERCENT_KEY);
        $minRelative = (int) ceil($priorCount * $minRelativePercent / 100);

        if ($drop < max($minAbsolute, $minRelative)) {
            return;
        }

        $title = 'Your Google review count dropped by '.$drop.' ('.$priorCount.' to '.$today
            .') — this can happen when a review is removed, not only when something is wrong.';

        MonitoringAlert::query()->create([
            'location_id' => $location->id,
            'alert_type' => 'review_loss.drop',
            'severity' => 'info',
            'title' => $title,
            'detail' => [
                'previous_review_count' => $priorCount,
                'review_count' => $today,
                'drop' => $drop,
                'previous_captured_at' => $previous->captured_at->toIso8601String(),
            ],
            'resolved_at' => CarbonImmutable::now(),
        ]);

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            (int) $location->id,
            ['automation' => 'visibility.competitor_signals', 'drop' => $drop],
            $title,
        );
    }

    /**
     * Whether the count has read identically for long enough, on an
     * established enough listing, to treat as a pause candidate — and, only
     * if the owner has opted in, to actually stop offering Google.
     */
    private function evaluatePause(Location $location, int $today): void
    {
        $open = MonitoringAlert::query()
            ->where('location_id', $location->id)
            ->where('alert_type', self::PAUSE_ALERT_TYPE)
            ->whereNull('resolved_at')
            ->orderByDesc('id')
            ->first();

        if ($open instanceof MonitoringAlert) {
            $this->reconsiderOpenPause($location, $open, $today);

            return;
        }

        if ($today < $this->registry->int(self::PAUSE_MIN_REVIEW_COUNT_KEY)) {
            return;
        }

        $flatSince = $this->flatRunStart($location, $today);

        if ($flatSince === null) {
            return;
        }

        $days = $flatSince->diffInDays(CarbonImmutable::now());

        if ($days < $this->registry->int(self::PAUSE_FLAT_DAYS_KEY)) {
            return;
        }

        $this->openPause($location, $today, $flatSince);
    }

    /**
     * The captured_at of the oldest row in the unbroken run of known readings
     * (working backward from the newest) that all equal $today — or null when
     * fewer than {@see self::PAUSE_MIN_FLAT_READS_KEY} such rows exist.
     *
     * ⚠️ **QUERIED BY DATE WINDOW, NEVER BY ROW LIMIT.** A `limit()` sized to
     * {@see self::PAUSE_MIN_FLAT_READS_KEY} would cap the visible run at that
     * many rows regardless of how much further back it actually goes — which
     * would make {@see self::PAUSE_FLAT_DAYS_KEY} unreachable whenever it asks
     * for more calendar days than that row count could span at a daily
     * cadence. The window is sized to the day requirement instead, with a
     * seven-day buffer so a run that started exactly on the boundary is not
     * truncated by it.
     */
    private function flatRunStart(Location $location, int $today): ?CarbonImmutable
    {
        $requiredDays = $this->registry->int(self::PAUSE_FLAT_DAYS_KEY);
        $minReads = $this->registry->int(self::PAUSE_MIN_FLAT_READS_KEY);

        $windowStart = CarbonImmutable::now()->subDays($requiredDays + 7);

        /** @var Collection<int, GoogleRatingSnapshot> $rows */
        $rows = GoogleRatingSnapshot::query()
            ->where('location_id', $location->id)
            ->whereNotNull('review_count')
            ->where('captured_at', '>=', $windowStart)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->get(['review_count', 'captured_at']);

        $streak = 0;
        $oldestInStreak = null;

        foreach ($rows as $row) {
            if ((int) $row->review_count !== $today) {
                break;
            }

            $streak++;
            $oldestInStreak = $row->captured_at;
        }

        if ($streak < $minReads || $oldestInStreak === null) {
            return null;
        }

        return $oldestInStreak;
    }

    private function openPause(Location $location, int $flatCount, CarbonImmutable $flatSince): void
    {
        $autoDisable = $this->registry->value(self::AUTO_DISABLE_ON_PAUSE_KEY) === true;

        $currentlyEnabled = $this->googleIsEnabled($location);

        $disabled = false;

        if ($autoDisable && $currentlyEnabled) {
            $this->destinations->disable($location, ReviewDestination::Google, self::ACTOR);
            $disabled = true;
        }

        $flatDays = $flatSince->diffInDays(CarbonImmutable::now());

        $title = $disabled
            ? 'Your Google review count has not moved in '.$flatDays
                .' days, so we stopped sending customers there until it does.'
            : 'Your Google review count has not moved in '.$flatDays
                .' days — Google may have paused new reviews on this listing.';

        MonitoringAlert::query()->create([
            'location_id' => $location->id,
            'alert_type' => self::PAUSE_ALERT_TYPE,
            'severity' => 'warning',
            'title' => $title,
            'detail' => [
                'review_count' => $flatCount,
                'flat_since' => $flatSince->toIso8601String(),
                'auto_disabled' => $disabled,
            ],
        ]);

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            (int) $location->id,
            ['automation' => 'visibility.competitor_signals', 'review_count' => $flatCount],
            $title,
        );
    }

    /**
     * An open pause alert exists. If today's count has moved, the pause has
     * ended — resolve it, and reverse our own disable if we made one and
     * nothing else has already reversed it.
     *
     * ⚠️ **NEVER RE-FIRES WHILE OPEN.** Only one pause alert is ever open per
     * location at a time, so a location that stays flat for months gets one
     * alert and one activity item, not one a day.
     */
    private function reconsiderOpenPause(Location $location, MonitoringAlert $open, int $today): void
    {
        $detail = is_array($open->detail) ? $open->detail : [];
        $flatCount = $detail['review_count'] ?? null;

        if (! is_int($flatCount) || $flatCount === $today) {
            return;
        }

        $open->forceFill(['resolved_at' => CarbonImmutable::now()])->save();

        $wasAutoDisabled = ($detail['auto_disabled'] ?? false) === true;

        if (! $wasAutoDisabled) {
            return;
        }

        // Re-enable only if it is still disabled — this system is the only
        // caller of disable() anywhere in app/, so if it is enabled here,
        // nothing needs reversing.
        if ($this->googleIsEnabled($location)) {
            return;
        }

        $this->destinations->enable($location, ReviewDestination::Google, null, self::ACTOR);

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            (int) $location->id,
            ['automation' => 'visibility.competitor_signals', 'review_count' => $today],
            'Your Google review count moved again, so we started sending customers there again.',
        );
    }

    private function googleIsEnabled(Location $location): bool
    {
        return $this->destinations->offeredFor($location)
            ->contains(fn ($setting): bool => $setting->destination === ReviewDestination::Google);
    }
}
