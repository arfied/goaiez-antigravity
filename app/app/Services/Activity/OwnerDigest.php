<?php

declare(strict_types=1);

namespace App\Services\Activity;

use App\Console\Commands\SendOwnerWeeklyDigests;
use App\Models\ActivityFeedItem;
use App\Models\Business;
use App\Models\Subscription;
use App\Services\Billing\Subscriptions;
use App\Services\Tenant\TenantSuspension;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * What a weekly digest to the account holder should say — automation #109 in
 * the catalog (`16-…-CATALOG.md` §12, "Weekly wins digest by SMS/email"),
 * email half only. Wave 39 lane C, decision rows 10726–10733.
 *
 * ⛔ **CONTENT BEFORE CADENCE, ON PURPOSE.** This class answers "is there
 * anything to say, and what" and knows nothing about schedules; the caller
 * ({@see SendOwnerWeeklyDigests}) decides when to ask.
 *
 * ⚠️ **THE SOURCE IS `activity_feed`, AND ONLY `action_type` AND `created_at`
 * ARE EVER READ.** `ActivityFeed`'s own rule — metadata is never rendered, in
 * whole or in part — needs no new argument here: this class never selects
 * `metadata`, so there is nothing to leak by construction rather than by
 * discipline.
 *
 * ⚠️ **NULL MEANS "SAY NOTHING", NOT "SAY THERE IS NOTHING"** — decision
 * 10730. A business with zero activity in its window gets no email at all,
 * on the argument that a recurring "nothing happened" message is a weekly
 * advertisement for cancelling and creates a signal that skipping does not.
 */
final class OwnerDigest
{
    public function __construct(
        private readonly TenantSuspension $suspension,
        private readonly Subscriptions $subscriptions,
        private readonly SiteDigest $site,
    ) {}

    /**
     * `FirstWeekPath`'s own Day-7 summary owns the first week of owner
     * communication (`STEP_DAY_THRESHOLD`'s last rung). This is a second,
     * independent constant rather than a shared one — the two classes have no
     * caller relationship and coupling them would make an unrelated change to
     * the onboarding ladder silently move this cutoff too — but the number is
     * the same on purpose (decision 10729) and a change to one is a reason to
     * re-read the other.
     */
    private const int EARLIEST_ELIGIBLE_AGE_DAYS = 7;

    /**
     * The widest window a single digest may cover — four digest cycles.
     *
     * ⛔ **A CAP EXISTS BECAUSE THE CURSOR'S FALLBACK IS `created_at` AND THAT
     * IS NOT A DIGEST BOUNDARY** (decision 10850). The column was deliberately
     * not backfilled — a backfill of a tenant-owned column cannot be an
     * `UPDATE` in a migration — so on the first run every business that
     * predates the feature reads null and {@see self::windowStart()} opens the
     * window at registration. On the eight accounts older than a week on the
     * running install that is the tenant's **entire history** arriving under a
     * subject line that says *this week*.
     *
     * ⚠️ **FOUR CYCLES RATHER THAN ONE, SO THE SELF-HEAL SURVIVES.** Clamping
     * to a flat seven days would delete the property {@see self::windowStart()}
     * was written for: a week skipped for having nothing to say, or a send
     * delayed by an outage, must widen the next window rather than lose the
     * days in between. Four cycles is wide enough that no ordinary outage,
     * pause or quiet fortnight loses a day, and narrow enough that the first
     * digest to an old account is a report rather than an archive.
     *
     * ⚠️ **WHAT IT COSTS IS STATED RATHER THAN HIDDEN.** Activity older than
     * this is never named in an email — for an account that registered in June
     * that is most of its history. It is not lost: `/account/activity` holds
     * all of it and is the one link this email carries. And it was never going
     * to be reported, because before wave 39 there was no digest at all.
     */
    private const int MAX_WINDOW_DAYS = 4 * self::EARLIEST_ELIGIBLE_AGE_DAYS;

    /**
     * How many distinct kinds of activity get their own line before the rest
     * are folded into "and N other things" — long enough to feel complete,
     * short enough to read on a phone.
     */
    private const int MAX_DISTINCT_LINES = 8;

    /**
     * Whether this business is due a digest right now.
     *
     * ⚠️ **TWO CONDITIONS OF CADENCE, AND BOTH FAIL CLOSED.** A business younger
     * than seven days is still inside `FirstWeekPath`'s own window — sending a
     * second, differently-shaped owner email in the same week is the overlap
     * decision 10729 refuses. A business with no digest yet, or one sent at
     * least seven days ago, is due; anything more recent is not.
     *
     * ⛔ **AND TWO OF STANDING, WHICH THIS METHOD DID NOT ASK UNTIL DECISION
     * 10854.** It read `created_at` and the cursor and **nothing else**, so a
     * suspended tenant and a cancelled one were as due as a paying one — and
     * the first digest to either arrives with a whole window behind it (10850)
     * under a greeting that says *here is what happened this week* and a
     * closing line that says *this is just so you can see it working.* On a
     * suspended account that sentence is false about the present tense; the
     * suspension is itself an `activity_feed` row, so the email would have
     * named `We put your account on hold` among the week's wins.
     *
     * ⚠️ **THE ENTITLEMENT ARM IS `Subscriptions::isEntitled()` AND NOT A NEW
     * QUESTION.** It is the same answer the monthly credit grant uses, it asks
     * {@see Subscription::accessHasEnded()} at read time, and
     * 8960–8979 is the ruling it inherits: a cancelled account went on being
     * granted and charged for ever because nothing revisited a status the
     * vendor never sends again. **A weekly email is the same shape at a
     * smaller cost.**
     *
     * ⛔ **A PAUSED TENANT IS DELIBERATELY NOT REFUSED HERE** — decision 10855,
     * upholding 10732. A pause stops *us acting*; every `AutopilotJob` skips,
     * so the window after a pause is empty and {@see self::compose()} declines
     * it without any help from this method. The one digest a pause does not
     * suppress is the first one after it, which reports work genuinely done in
     * the days **before** the owner pressed stop — and that is the report most
     * worth having when deciding whether to resume. Refusing it would lose
     * that week for good.
     *
     * ⚠️ **BOTH NEW ARMS RUN LAST, SO THE TWENTY-NINE QUIET DAYS STAY CHEAP.**
     * The cadence checks read the row already in hand; these two reach the
     * database, and only for a business that is actually due.
     *
     * ⚠️ **A TENANT IS REQUIRED, AND SAYING SO IS THE POINT.** Both new arms
     * read tenant-owned rows through the ordinary global scope, so this method
     * is no longer answerable outside `Tenancy::actingAs()` — the same
     * declaration {@see self::compose()} already makes, moved one method
     * earlier because the requirement moved with it.
     */
    public function eligible(Business $business): bool
    {
        Tenancy::idOrFail();

        $createdAt = $business->created_at;

        if (! $createdAt instanceof Carbon) {
            return false;
        }

        if ($createdAt->gt(now()->subDays(self::EARLIEST_ELIGIBLE_AGE_DAYS))) {
            return false;
        }

        $lastSent = $business->owner_digest_sent_at;

        if ($lastSent !== null && $lastSent->gt(now()->subDays(self::EARLIEST_ELIGIBLE_AGE_DAYS))) {
            return false;
        }

        if ($this->suspension->isSuspended($business)) {
            return false;
        }

        return $this->subscriptions->isEntitled($business);
    }

    /**
     * The start of the window this digest covers.
     *
     * ⚠️ **THE BUSINESS'S OWN CURSOR, NEVER A FIXED SEVEN DAYS.** A business
     * whose last digest was skipped for having nothing to say, or whose send
     * was delayed by an outage, gets a WIDER window on the next attempt
     * rather than losing the days in between — `credits:reset-monthly`'s own
     * self-healing argument, applied to a read instead of a grant.
     *
     * ⛔ **WIDER, BUT NOT WITHOUT LIMIT — {@see self::MAX_WINDOW_DAYS}**
     * (decision 10850). The cursor is null on every business that predates the
     * column, so *the business's own cursor* is `created_at` on the first run
     * and the window is the whole account. The clamp is applied to every
     * window rather than only to the first, because the same unbounded width
     * arrives from a long pause, a suspension or a lapsed subscription — and
     * a rule that holds for one arm and not the others is the kind a later
     * lane deletes as an inconsistency.
     *
     * ⚠️ **THE RETURN IS A COPY.** `Illuminate\Support\Carbon` is mutable and
     * this is the model's own attribute instance; handing it out unguarded
     * means any caller's `->addDays()` silently moves the cursor this method
     * exists to read.
     */
    public function windowStart(Business $business): Carbon
    {
        // `now()` is unreachable in practice — every business row is created
        // with a `created_at` — and is here only because the column is
        // nullable at the type level and this method must return something.
        $cursor = $business->owner_digest_sent_at ?? $business->created_at ?? now();
        $cursor = $cursor instanceof Carbon ? $cursor->copy() : Carbon::instance($cursor);

        $earliest = now()->subDays(self::MAX_WINDOW_DAYS);

        return $cursor->lt($earliest) ? $earliest : $cursor;
    }

    /**
     * What happened, grouped by the closed vocabulary and counted — or null
     * when there is nothing to report.
     *
     * ⚠️ **`get()`, NEVER `pluck()`, ON THE ONE ROW-KEY THAT CANNOT BE ONE.**
     * `action_type` casts to a backed enum, and an enum instance cannot be a
     * PHP array key — `pluck('total', 'action_type')` would have to coerce it
     * back to a string, throwing away the cast this whole method exists to
     * use. Hydrating full (if narrow) models keeps the cast and reads the
     * enum straight off each row instead.
     *
     * @return ?array{since: Carbon, total: int, lines: list<string>, overflow: int}
     */
    public function compose(Business $business): ?array
    {
        Tenancy::idOrFail();

        $since = $this->windowStart($business);
        $siteLines = $this->site->lines((int) $business->id, $since);

        $rows = ActivityFeedItem::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('action_type, count(*) as total')
            ->groupBy('action_type')
            ->get()
            ->sortByDesc(fn (ActivityFeedItem $row): int => (int) $row->getAttribute('total'));

        if ($rows->isEmpty() && $siteLines === []) {
            return null;
        }

        $lines = [];
        $overflow = 0;
        $total = 0;

        foreach ($rows as $row) {
            $count = (int) $row->getAttribute('total');
            $total += $count;

            if (count($lines) >= self::MAX_DISTINCT_LINES) {
                $overflow += $count;

                continue;
            }

            $title = $row->action_type->title();
            $lines[] = $count > 1 ? "{$title} ({$count} times)" : $title;
        }

        // MAX_DISTINCT_LINES governs autopilot lines only
        $lines = [...$lines, ...$siteLines];

        return [
            'since' => $since,
            'total' => $total,
            'lines' => $lines,
            'overflow' => $overflow,
        ];
    }
}
