<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\ReviewSource;
use App\Models\ActivityFeedItem;
use App\Models\AutomationRun;
use App\Models\Review;
use App\Services\ActivityService;
use App\Services\Ai\AiSpend;
use App\Services\AuditService;
use App\Services\Reviews\ModerationVerdict;
use App\Services\Reviews\PhiAnalysisConsent;
use App\Services\Reviews\ReviewAnalyzer;
use App\Services\Reviews\ReviewInsights;
use App\Services\Reviews\ReviewModerator;

/**
 * Moderation, then sentiment and themes, for one first-party review (`17` FPR-02).
 *
 * AN AutopilotJob RATHER THAN A PLAIN QUEUED JOB, and the contrast with
 * PublicAuditJob is the argument. That one runs exactly once because every retry
 * is another 9.2c against a platform budget spent by strangers (decision 228).
 * This one is per tenant, costs about 0.29c, and its cap has three orders of
 * magnitude of headroom — so 228's reasoning does not transfer, and what
 * AutopilotJob supplies does: the tenant a queued job would otherwise lack, the
 * kill switch, the idempotency key, and a run row that records the outcome
 * whatever it was.
 *
 * `tries` AND `backoff()` ARE LIVE ON THE THROW PATH AND INERT ON EVERY OTHER
 * ONE — AND THIS DOCBLOCK HAS NOW BEEN WRONG IN BOTH DIRECTIONS. It first
 * claimed the retries worked; decision 356 corrected that to "inert", which was
 * true while the claim outlived the failure. Moving the release into a `finally`
 * changed it again: on a *thrown* exception the key is null by the time the
 * queue redelivers, so the redelivery genuinely re-executes and opens a second
 * `automation_runs` row. Three tries, up to three rows, from one dispatch.
 *
 * On every other exit — an exhausted cap, an unreadable verdict, a Google row —
 * the job returns normally, the queue has nothing to retry, and what lets the
 * review be looked at again is releaseUnearnedClaim() below plus a later
 * dispatch that re-claims the key once it is null.
 *
 * `reviews:reanalyse` COUNTS THOSE ROWS, so the two facts are connected: its
 * ceiling excludes handed-off and skipped runs and is sized for three rows per
 * dispatch. Read MAX_ATTEMPTS there before changing `tries` here.
 *
 * AND SOMETHING HAS TO DO THAT DISPATCHING, which decision 351 established the
 * need for and did not build. Releasing a claim nobody re-claims is a gap made
 * visible rather than closed. Two callers exist now: `reviews:reanalyse` on the
 * schedule, and FeedbackSubmission's collapse path, which re-dispatches when the
 * review a resubmission collapsed into is still unmoderated — because
 * resubmitting the same words is exactly what a person does after a submission
 * that appeared to go nowhere.
 *
 * THE THROW PATH IS A NO-VERDICT PATH TOO, and it was the invisible one. On a
 * thrown exception AutopilotJob marks the run failed and rethrows, so
 * recordActivity() never runs — the review was buried *and* unmentioned. Hence a
 * `finally` rather than two enumerated branches, and hence noteAwaitingModeration()
 * posting the feed item itself instead of going through activityAction().
 *
 * TWO CALLS, AND MODERATION GATES ANALYSIS. Flagged text goes to a person who
 * reads the words anyway, so themes for something nobody will display buy
 * nothing — and it skips the expensive half on exactly the inputs most likely to
 * be adversarial.
 *
 * DISPLAY FAILS CLOSED. ROUTING FAILS OPEN. Nothing here writes `status` or
 * `routing_decision`: slice E owns both, and it carries a build-failing test that
 * a below-threshold customer always reaches triage. If an exhausted monthly cap
 * could strand a 2-star review here, that test would pass in CI — where nothing
 * is capped — and fail in production. So every outcome leaves an identical
 * routing state, and Review::displayable() is where the AI outcome is felt.
 *
 * GOOGLE REVIEWS ARE NEVER TOUCHED. `29` §2 rule 1: never held, hidden,
 * approved, or moderated. The guard is here rather than at the dispatch site
 * because a dispatch site can be added; this cannot be bypassed by adding one.
 * It is no longer the only layer: `reviews_google_is_never_moderated` is a CHECK
 * on the table, because `$guarded` leaves `source`, `moderation_flags` and
 * `flagged_at` mass-assignable and a repair script reaches neither this guard
 * nor any other.
 *
 * ⚠️ A PHI TENANT'S REVIEW REACHES A MODEL ONLY IF ITS OWN AUTHOR AGREED TO
 * LEAVE HEALTH INFORMATION OUT (2079-2081, moving decision 421's gate from per
 * tenant to per review). This job sends free-text customer comments to a
 * third-party provider, and a comment written by a patient about a dental or
 * medical practice is PHI the moment that practice is the tenant. `29` §2 rule
 * 24 forbids it without an executed BAA, and the subprocessor inventory records
 * that no BAA exists with either provider.
 *
 * ⚠️ THE DEFAULT IS STILL WITHHOLD, AND THAT IS THE HALF TO KEEP. A review with
 * no `review_phi_consents` row is refused exactly as decision 421 refused every
 * review of every covered entity — absence of a record is a refusal, never a
 * permission. What the ruling changed is that a reviewer can now say yes; it did
 * not change what happens when nobody says anything.
 *
 * 362 declined to build the original gate because "a gate with no tenant to gate
 * matches nothing and passes vacuously". **Both halves of that reasoning had
 * expired:** the owner put healthcare in the launch market on 2026-08-03, and
 * `DataClassification::Phi` plus `businesses.data_classification` already
 * existed — so a test can create a PHI business today and assert that no request
 * leaves. The gate is falsifiable, which is what 256 and 285 actually required.
 *
 * ⚠️ IT ROUTES TO `handoff()` RATHER THAN THROWING, and the distinction is the
 * whole design. A PHI tenant is not a failure to recover from — it is a tenant
 * whose reviews a human reads instead. `29` §2 rule 44 already required this
 * path to exist, so the gate is a `canExecute()` answer rather than a new
 * mechanism.
 */
final class AnalyzeReviewJob extends AutopilotJob
{
    /** @var list<string> */
    private array $withheldFlags = [];

    private bool $withheld = false;

    /**
     * True once a real verdict is in the column and the claim has been earned.
     *
     * The inverse is what the `finally` acts on, and stating it this way round
     * is the fix: the old code enumerated the two paths it knew about, so a
     * third — a thrown exception — kept the claim and buried the review.
     */
    private bool $verdictPersisted = false;

    /**
     * Memoised answer to "may this tenant's content reach a model at all".
     */
    private ?bool $phiWithheld = null;

    /**
     * Whether this job found a review it is allowed to touch at all.
     *
     * Separates "no model looked" from "there was nothing to look at". A Google
     * row and a deleted row both leave without a verdict, and neither is
     * something to tell an owner about.
     */
    private bool $reviewFound = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $reviewId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'review.analyze';
    }

    /**
     * One analysis per review, ever.
     *
     * The database is the arbiter, so a duplicate dispatch — a retry, a
     * double-tap that slipped past FeedbackSubmission's collapse, a replayed
     * queue message — cannot bill twice.
     */
    protected function idempotencyKey(): string
    {
        return 'analyze-review:'.$this->reviewId;
    }

    /**
     * Whether an AI call is possible at all.
     *
     * Checked here rather than discovered inside execute() so that an exhausted
     * cap is recorded as a **handoff** rather than as a success that happened to
     * do nothing. The run row is the record somebody reads afterwards, and those
     * are different facts.
     */
    protected function canExecute(): bool
    {
        // ⚠️ PHI FIRST, AND THE ORDER IS LOAD-BEARING. A PHI tenant's cap is
        // irrelevant — no call is permitted at any spend — and asking `AiSpend`
        // first would make the reason recorded on the run row depend on how much
        // budget happened to be left. The two facts are different and only one
        // of them is a compliance boundary.
        return ! $this->phiWithheld() && app(AiSpend::class)->allows();
    }

    /**
     * Whether *this review's* words may not be sent to a model.
     *
     * ⚠️ PER REVIEW SINCE 2079-2081, NOT PER TENANT. The question used to be
     * "is this business a covered entity", read straight off
     * `businesses.data_classification` — a column that had existed since Stage 0
     * with no reader anywhere in `app/` until decision 421 gave it one. It is
     * now "is this business a covered entity **and** did this reviewer agree to
     * leave health information out", and `PhiAnalysisConsent` owns both halves.
     * See that class for why the read moved out of this file rather than being
     * duplicated a fourth time, and for what a consent row does not do.
     *
     * Memoised because `canExecute()` and `handoff()` both ask. The answer
     * cannot change inside one job run: neither a tenant's classification nor a
     * reviewer's undertaking is something a review's own processing alters.
     */
    private function phiWithheld(): bool
    {
        return $this->phiWithheld ??= app(PhiAnalysisConsent::class)
            ->withholds($this->businessId, $this->reviewId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        try {
            return $this->moderateAndAnalyse();
        } finally {
            $this->releaseUnearnedClaim();
        }
    }

    /**
     * The same review, without an AI call.
     *
     * Not a stub. The review stays unmoderated — which is a real state, not an
     * error — so it is withheld from display and handed to routing untouched,
     * and the run row says why. `29` §2 rule 44 wants this path built in the same
     * ticket as execute(), because retrofitting it means touching every
     * automation twice.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        try {
            $review = $this->reviewToAnalyse();

            if (! $review instanceof Review) {
                return ['skipped' => 'not_analysable'];
            }

            // ⚠️ TWO REASONS, NOT ONE, AND THE RUN ROW IS WHERE SOMEBODY LEARNS
            // WHICH. `ai_unavailable` is a condition that clears — a cap resets
            // on the 1st, an outage ends — and the sweep exists to pick those
            // up. `phi_withheld` never clears: it is the correct permanent state
            // for this tenant, and recording it as an outage would send whoever
            // reads the row looking for a provider incident that never happened.
            return [
                'moderated' => false,
                'reason' => $this->phiWithheld() ? 'phi_withheld' : 'ai_unavailable',
            ];
        } finally {
            $this->releaseUnearnedClaim();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function moderateAndAnalyse(): array
    {
        $review = $this->reviewToAnalyse();

        if (! $review instanceof Review) {
            return ['skipped' => 'not_analysable'];
        }

        $verdict = app(ReviewModerator::class)->moderate($review->rating, $review->comment);

        $this->persistVerdict($review, $verdict);

        if (! $verdict->wasModerated()) {
            // No model looked. `moderation_flags` stays null, which
            // Review::displayable() reads as "not displayable" — and routing is
            // untouched, so slice E sees exactly what it would have seen. The
            // claim goes back in the `finally`, so a later dispatch is not a
            // permanent no-op.
            return ['moderated' => false, 'reason' => $verdict->reason];
        }

        if ($verdict->isFlagged()) {
            $this->withheld = true;
            $this->withheldFlags = $verdict->flagValues();
            $this->recordWithholding($review);

            return ['moderated' => true, 'withheld' => true, 'flags' => $this->withheldFlags];
        }

        return ['moderated' => true, 'withheld' => false] + $this->analyse($review);
    }

    /**
     * Write the verdict, and never write over a better one.
     *
     * THIS USED TO BE TWO UNCONDITIONAL ASSIGNMENTS, which was safe only while
     * nothing could re-run. A re-dispatcher makes both reachable: an
     * `unavailable` verdict on a second pass would write null over a completed
     * flag list, and a `clean` verdict from a non-deterministic model would
     * clear `flagged_at` and publish a review a person had already been told was
     * held. Neither column is ever moved back toward visible here — a second
     * opinion may add a withholding, never remove one.
     *
     * `$verdictPersisted` is set even when nothing was written, because a column
     * that already holds a verdict means the work this claim protects has
     * happened; giving the key back then would invite a second bill for it.
     */
    private function persistVerdict(Review $review, ModerationVerdict $verdict): void
    {
        if (! $verdict->wasModerated()) {
            return;
        }

        if ($review->moderation_flags === null) {
            $review->moderation_flags = $verdict->forStorage();
        }

        if ($verdict->isFlagged() && $review->flagged_at === null) {
            $review->flagged_at = now();
        }

        $review->save();

        $this->verdictPersisted = true;
    }

    /**
     * Sentiment and themes, and the one gap `reviews:reanalyse` deliberately
     * does not close.
     *
     * When moderation cleared and this half then failed — a refusal, an
     * unparseable body, a 500 on the second call — `sentiment` and `themes` stay
     * null forever. The sweeper will not pick that up, and that is a choice
     * rather than an oversight:
     *
     *   - The claim is correctly retained. A verdict was persisted and a model
     *     was billed for it, so re-dispatching would re-run moderation to
     *     recover analysis, paying twice for the half that already worked.
     *   - The consequence is not publication. The review is displayable,
     *     routable, and readable; what is missing is two internal analytics
     *     columns. An unmoderated review is invisible to the public, which is
     *     why that one is swept and this one is not.
     *
     * Closing it properly means a per-half idempotency key, which is a second
     * `automation_runs` row per review and a schema-shaped decision. When
     * somebody wants themes badly enough to pay for that, this is the paragraph
     * to argue with.
     *
     * @return array<string, mixed>
     */
    private function analyse(Review $review): array
    {
        $insights = app(ReviewAnalyzer::class)->analyze($review->rating, $review->comment);

        if (! $insights instanceof ReviewInsights) {
            return ['analysed' => false];
        }

        $review->sentiment = $insights->sentiment;
        $review->themes = $insights->themeValues();
        $review->save();

        return [
            'analysed' => true,
            'sentiment' => $insights->sentiment->value,
            'themes' => $insights->themeValues(),
        ];
    }

    /**
     * The base class's question, answered with the flag this job already kept.
     *
     * ⚠️ THIS JOB'S OWN `releaseUnearnedClaim()` BELOW STAYS, AND THE OVERLAP IS
     * DELIBERATE. The base class now releases on the same condition, so the
     * `UPDATE` may run twice — it is idempotent and costs one statement. What the
     * base cannot do is `noteAwaitingModeration()`, which is this job's alone and
     * is the half 351/356/357 were actually about: the owner being told a review
     * is waiting. Folding the two would either lose that notice or push a
     * review-shaped concern into the base class every automation inherits.
     */
    protected function claimIsSpent(): bool
    {
        return $this->verdictPersisted;
    }

    /**
     * Give back the idempotency claim, because nothing was spent.
     *
     * A key exists to stop work being repeated. It is claimed before execute()
     * so that two workers cannot both bill for the same review — but where no
     * model ever looked, there is no work to protect, and a claim held forever
     * means an hour of vendor downtime permanently buries every review submitted
     * during it: `moderation_flags` stays null, so Review::displayable() hides
     * it, and every later dispatch is a no-op against the unique index.
     *
     * CALLED FROM A `finally`, ON THE ONE CONDITION THAT MATTERS. Decision 351
     * released on two named branches — an exhausted cap and a vendor outage —
     * and a thrown exception is a third, which kept its claim and was buried
     * with nothing in the feed to say so. The condition is now "no verdict was
     * persisted", which cannot miss a branch nobody thought of.
     *
     * A completed moderation keeps its claim, which is exactly what stops a
     * redelivery paying twice.
     */
    private function releaseUnearnedClaim(): void
    {
        if ($this->verdictPersisted) {
            return;
        }

        AutomationRun::query()
            ->where('automation_key', $this->automationKey())
            ->where('idempotency_key', $this->idempotencyKey())
            ->update(['idempotency_key' => null]);

        $this->noteAwaitingModeration();
    }

    /**
     * Tell the owner once that a review is waiting on a model.
     *
     * ONCE PER REVIEW, NOT ONCE PER RUN, and the sweeper is why. `claimRun()`
     * opens a fresh `automation_runs` row on every dispatch, so a five-minute
     * sweep across a one-hour outage gives one review twelve runs — twelve
     * identical feed items is a feed nobody reads, on the exact day it matters
     * most. The run rows stay, because they are the exhaustive record and the
     * sweeper's own attempt ceiling counts them.
     *
     * Posted here rather than through activityAction() because
     * AutopilotJob::recordActivity() never runs on the throw path, and the throw
     * path is the one where silence is worst.
     */
    private function noteAwaitingModeration(): void
    {
        if (! $this->reviewFound) {
            return;
        }

        // whereRaw with `->>` rather than Eloquent's `metadata->review_id`
        // sugar: the operator returns text, so the comparison is a string one
        // and says so. The scoped builder still carries the tenant predicate,
        // and RLS sits under it either way.
        //
        // THE AUTOMATION KEY IS PART OF THE PREDICATE, and leaving it out was a
        // trap laid for whoever writes the next automation. `OwnerActionNeeded`
        // is a shared action type — TokenService already posts one — so any
        // future automation posting it with a `review_id` in its metadata would
        // silently suppress this notice for that review, and the failure mode is
        // an owner never being told their customer's feedback is stuck.
        $alreadyTold = ActivityFeedItem::query()
            ->where('action_type', AutopilotActionType::OwnerActionNeeded->value)
            ->whereRaw("metadata->>'automation' = ?", [$this->automationKey()])
            ->whereRaw("metadata->>'review_id' = ?", [(string) $this->reviewId])
            ->exists();

        if ($alreadyTold) {
            return;
        }

        app(ActivityService::class)->record(
            AutopilotActionType::OwnerActionNeeded,
            $this->locationId,
            [
                'automation' => $this->automationKey(),
                'review_id' => $this->reviewId,
            ],
        );
    }

    /**
     * The append-only record that a customer's words were withheld.
     *
     * `29` §2 rule 42: every sensitive action reaches the audit log. Suppressing
     * a named customer's writing from publication is the most consequential act
     * in this slice, and until now the only traces were an activity-feed row —
     * the owner's view, in the owner's language — and an `automation_runs` row
     * that is UPDATEd twice and is therefore not append-only at all. Slice A set
     * the precedent in the other direction (decision 297: every consent event,
     * with a required actor).
     *
     * 'autopilot' as the actor, because no person decided this. NEVER THE REVIEW
     * TEXT: the flags say why, the entity reference says which row, and the
     * words themselves are one lookup away for anyone entitled to read them.
     */
    private function recordWithholding(Review $review): void
    {
        app(AuditService::class)->record(
            'review.withheld',
            'autopilot',
            $review,
            [
                'flags' => $this->withheldFlags,
                'source' => $review->source->value,
            ],
        );
    }

    /**
     * The review, if it exists and is ours to moderate.
     *
     * Returns null for a Google-sourced row rather than throwing: rule 1 makes
     * that a no-op, not a failure, and a throw would retry three times and then
     * mark a run failed for behaving correctly.
     */
    private function reviewToAnalyse(): ?Review
    {
        $review = Review::query()->find($this->reviewId);

        if (! $review instanceof Review) {
            return null;
        }

        if ($review->source !== ReviewSource::FirstParty) {
            return null;
        }

        $this->reviewFound = true;

        return $review;
    }

    /**
     * What the run row carries about the thing it acted on.
     *
     * The review id is here so `reviews:reanalyse` can count this automation's
     * previous attempts for one review and stop after a ceiling. Without it the
     * sweeper cannot tell a review it has tried twice from one it has tried
     * fifty times, and a permanently-failing row would be re-dispatched every
     * schedule tick forever.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + ['review_id' => $this->reviewId];
    }

    /**
     * Silence unless something was withheld.
     *
     * AutopilotJob's own docblock names the automation "whose only honest title
     * is 'checked something and found nothing'" as the one that makes the feed
     * worse. An analysis that cleared is exactly that. A withheld review is not:
     * somebody left feedback the owner cannot see, and that is theirs to know.
     *
     * The "waiting on a model" case is not absent — it moved to
     * noteAwaitingModeration(), which posts it once per review and works on the
     * throw path, where this method is never reached at all.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->withheld ? AutopilotActionType::ReviewHeld : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityMetadata(): array
    {
        // NEVER THE REVIEW TEXT, which here is by construction the text a model
        // just judged unfit to display. ⚠️ This used to justify itself with "it
        // reaches a broadcast payload, and from there browser memory and
        // devtools" — it does not: ActivityRecorded::broadcastWith() is a
        // four-key allowlist and metadata never goes on the wire. The rule holds
        // for the reasons that are true — the payload must be safe if it is ever
        // broadcast, the feed is owner-visible, and the audit log is what an
        // erasure request has to honour — and withheld text has the shortest
        // possible list of places it belongs.
        return [
            'automation' => $this->automationKey(),
            'review_id' => $this->reviewId,
            'flags' => $this->withheldFlags,
        ];
    }
}
