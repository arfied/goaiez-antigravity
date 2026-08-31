<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Enums\AutopilotActionType;
use App\Enums\ReviewSource;
use App\Jobs\AutopilotJob;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Models\TriageConversation;
use App\Services\Ai\AiSpend;
use App\Services\Reviews\PhiAnalysisConsent;
use App\Services\Reviews\ReplyDraft;
use App\Services\Reviews\ReplyGenerator;
use App\Services\Reviews\ReviewRouter;
use RuntimeException;

/**
 * Write the owner a win-back message for one triaged review (T176 P15, TRIAGE-02).
 *
 * ⛔ **THE AUDIT THAT PRODUCED THIS JOB FOUND NOTHING TO CONFIRM** (4350). P15
 * asked whether AI drafting already existed on the recovery/triage outreach
 * path. Traced end to end it did not, and every step said so in its own words:
 * `ReviewRouter::openTriage()` writes `transcript => []`; the model's docblock
 * records that nothing appends to it (942); `ai_paused` had a writer and no
 * reader in `app/` (2706); `WinBack` offers five outcome buttons, a takeover
 * toggle and a notes box, and calls no AI; and `ReplyGenerator`'s only consumer
 * refuses anything but a Google review at the writer boundary. The recovery path
 * reached the owner with the customer's complaint and no words to answer it.
 *
 * ⛔ **IT DRAFTS AND IT DOES NOT SEND, AND NOTHING IN THIS SLICE SENDS.** The
 * message is stored on the conversation and rendered on the recovery queue for
 * the owner to copy into whatever they already use to reach that customer. There
 * is no channel derivation, no consent read, no suppression check, no arbiter
 * and no `outreach_messages` row — see the migration for why that last refusal
 * is the load-bearing one.
 *
 * ⚠️ **CONFIRM DOES NOT GOVERN IT, AND IS NOT WIDENED.** CONFIRM is reserved for
 * GBP identity changes, spending money, and the first send of a new campaign
 * type. This changes no listing, is not a send at all, and its AI cost sits
 * inside the monthly grant exactly as `AnalyzeReviewJob` and `GenerateReplyJob`
 * do — `AiRouter::dispatch()` records the spend on the same meter they use. So
 * it runs on the ungated default like every other automation, behind the kill
 * switch, the suspension, the pause and the toggles `AutopilotJob` already
 * checks.
 *
 * ⛔ **AND `automation_mode` DOES NOT GOVERN IT EITHER, WHICH THIS PARAGRAPH AND
 * DECISION 4354 BOTH CLAIMED UNTIL 4467.** The column has a cast, an admin form
 * field on `LocationSettings`, a mention in `AssistantToggle`'s docblock — and
 * **no reader anywhere in `app/`**. `AutopilotJob::isEnabled()` returns `true`
 * unconditionally, so *every* automation in this codebase runs regardless of
 * what an owner set it to, and naming it here described a gate that does not
 * exist. **2660 is exactly this shape**: a ruling with no code reads identically
 * to a ruling with code, the next lane cites it, and a lint against it would
 * pass vacuously. The claim is struck rather than implemented — wiring a reader
 * changes the behaviour of every automation at once and is nobody's fix wave.
 * What actually gates this job is the list in the paragraph above.
 *
 * ⚠️ **A PAUSED OR SUSPENDED TENANT GETS NO DRAFT, AND THE CONVERSATION STILL
 * OPENS.** `AutopilotJob` skips on both, which is the correct answer for a job
 * that spends money on a tenant's behalf while they have asked us to stop —
 * and it does not touch decision 114, because `ReviewRouter` opens triage
 * regardless of either stop and this job is not the recovery path. It is the
 * words on it.
 */
final class DraftRecoveryOutreachJob extends AutopilotJob
{
    private string $handoffReason = 'unknown';

    private ?bool $phiWithheld = null;

    /** True once a draft was stored — the claim is then earned. */
    private bool $draftRecorded = false;

    /** True when this review must never draft again. */
    private bool $permanentlySkipped = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $reviewId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'reviews.draft_recovery_outreach';
    }

    protected function idempotencyKey(): string
    {
        return 'draft-recovery-outreach:'.$this->reviewId;
    }

    protected function claimIsSpent(): bool
    {
        return $this->draftRecorded || $this->permanentlySkipped;
    }

    /**
     * ⚠️ SILENT IN THE FEED, AND THAT IS `recordDecision()`'s ARGUMENT REUSED.
     * `TriageOpened` and `OwnerActionNeeded` were both filed at routing time and
     * point at this very conversation; a third item saying its draft is ready
     * would be two notifications for one event, sending the owner to a screen
     * they have already been sent to. `automation_runs` is the exhaustive record.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    protected function canExecute(): bool
    {
        $review = $this->review();

        if ($review === null) {
            $this->handoffReason = 'review_missing';
            $this->permanentlySkipped = true;

            return false;
        }

        // ⚠️ FIRST-PARTY ONLY, AND IT IS NOT REDUNDANT WITH THE TRIAGE CHECK
        // BELOW. `route()` refuses to route a Google review at all, so no Google
        // row can carry a triaged decision today — but that is a fact about
        // another class, and slice I's importer is written to hand `route()`
        // Google rows in bulk. A guard that is only true because of somebody
        // else's early return is 398's unfalsifiable inner guard, so this one
        // asks its own question.
        if ($review->source !== ReviewSource::FirstParty) {
            $this->handoffReason = 'not_first_party';
            $this->permanentlySkipped = true;

            return false;
        }

        // The gate is the routing decision rather than a second reading of the
        // threshold. `FeedbackSubmission` dispatches this unconditionally on
        // `SendReviewInviteJob`'s stated reasoning — a copy of the triage rule
        // in the submission path is a copy that can disagree with the router's.
        if ($review->routing_decision?->triaged() !== true) {
            $this->handoffReason = 'not_triaged';
            $this->permanentlySkipped = true;

            return false;
        }

        $conversation = $this->conversation();

        if (! $conversation instanceof TriageConversation) {
            $this->handoffReason = 'no_conversation';
            $this->permanentlySkipped = true;

            return false;
        }

        // ⚠️ A DRAFT IS WRITTEN ONCE AND NEVER OVERWRITTEN. The owner may have
        // read it, copied it, edited it in their own client and sent it an hour
        // ago; regenerating would replace what they are working from with
        // different words and give them no way to tell. The idempotency key
        // already makes a redelivery a no-op — this is the guard for a
        // *deliberate* second dispatch, which the duplicate-submission path
        // makes reachable.
        if ($conversation->outreach_draft_at !== null) {
            $this->handoffReason = 'already_drafted';
            $this->permanentlySkipped = true;

            return false;
        }

        // ⚠️ THE FIRST PATH WHERE 2079-2081's PER-REVIEW ANSWER ACTUALLY VARIES.
        // `GenerateReplyJob`'s own docblock says the gate is a constant "withhold"
        // there, because a Google reviewer never saw our feedback page and can
        // never have a consent row. This review's author did see it, and may have
        // ticked the box — so a covered entity gets a model-written message for
        // the reviewers who consented and the platform template for the rest.
        if ($this->phiWithheld()) {
            $this->handoffReason = 'phi_withheld';

            return false;
        }

        if (! app(AiSpend::class)->allows()) {
            $this->handoffReason = 'ai_cap_exhausted';

            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $review = $this->review();
        $conversation = $this->conversation();

        if ($review === null || ! $conversation instanceof TriageConversation) {
            // Unreachable through `canExecute()`, which asked both questions —
            // kept because `execute()` re-reads rather than trusting a property
            // set during the gate, and a null here would otherwise be a fatal.
            $this->permanentlySkipped = true;

            return ['outcome' => 'unavailable', 'reason' => 'review_or_conversation_missing'];
        }

        $location = Location::query()->find($review->location_id);
        $business = Business::query()->find($this->businessId);

        if ($location === null || $business === null) {
            return ['outcome' => 'unavailable', 'reason' => 'location_or_business_missing'];
        }

        $draft = app(ReplyGenerator::class)->draftRecovery($review, $location, $business);

        app(ReviewRouter::class)->recordOutreachDraft($conversation, $draft);

        // ⚠️ THE CLAIM IS EARNED ONLY BY AN ANSWER, AND THE DRAFT IS STORED
        // EITHER WAY (1682's rule, GenerateReplyJob's shape). An outage decided
        // nothing, so the key goes back and the throw sends this through
        // `backoff()` — but the owner still has a message on the screen in the
        // meantime, because a blank card is worse than a plain apology.
        //
        // ⚠️ A RETRY THEN HAS TO GET PAST `already_drafted`, AND IT DOES NOT.
        // That is deliberate and it is the trade this slice takes knowingly: the
        // owner may already be working from the template. The alternative —
        // silently replacing text somebody may have copied — is the worse half,
        // and `automation_runs` carries the fallback reason so the retry that
        // hands off says which draft the owner is looking at.
        $this->draftRecorded = ! $draft->retryable;

        if ($draft->retryable) {
            throw new RuntimeException(
                'Recovery drafting fell back to the safe template ('
                .(string) $draft->fallbackReason.'); retrying.',
            );
        }

        return [
            'outcome' => 'drafted',
            'triage_conversation_id' => (int) $conversation->id,
            'from_model' => $draft->fromModel,
            'fallback_reason' => $draft->fallbackReason,
        ];
    }

    /**
     * The same outcome without provider access — `29` §2 rule 44.
     *
     * ⚠️ **NOT A STUB AND NOT AN APOLOGY.** For the two reasons where a message
     * is still the right thing — a covered entity whose reviewer did not consent
     * to analysis, and an exhausted AI balance — the owner gets the
     * platform-owned template, which invents nothing, offers nothing and asks
     * for nothing. That is the whole point of the handoff path: the recovery
     * engine has to work with the model unavailable, and an owner with a plain
     * apology to copy is an owner who can still reach their customer.
     *
     * ⚠️ **AND IT IS NARROW ON PURPOSE.** The other four reasons are "there is
     * nothing to draft for" — no review, not first-party, not triaged, already
     * drafted — and writing a template into any of them would put a message
     * about a complaint on a conversation that has one already, or on nothing.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        if (! in_array($this->handoffReason, ['phi_withheld', 'ai_cap_exhausted'], true)) {
            return ['outcome' => 'handoff', 'reason' => $this->handoffReason];
        }

        $review = $this->review();
        $conversation = $this->conversation();
        $business = Business::query()->find($this->businessId);

        if ($review === null || ! $conversation instanceof TriageConversation || $business === null) {
            return ['outcome' => 'handoff', 'reason' => $this->handoffReason];
        }

        $generator = app(ReplyGenerator::class);

        $draft = ReplyDraft::fallback(
            $generator->recoverySafeTemplate(
                businessName: (string) $business->name,
                reviewerLabel: $generator->reviewerLabel($review->reviewer_name),
            ),
            $this->handoffReason,
        );

        app(ReviewRouter::class)->recordOutreachDraft($conversation, $draft);

        $this->draftRecorded = true;

        return [
            'outcome' => 'handoff',
            'reason' => $this->handoffReason,
            'triage_conversation_id' => (int) $conversation->id,
            'from_model' => false,
        ];
    }

    private function review(): ?Review
    {
        return Review::query()->find($this->reviewId);
    }

    /**
     * Through `ReviewRouter`, never `TriageConversation` directly — 2700's seam,
     * and `Architecture\ReviewsTest` fails the build on the shortcut.
     */
    private function conversation(): ?TriageConversation
    {
        return app(ReviewRouter::class)->conversationForReview($this->reviewId);
    }

    /**
     * ⛔ `withholdsDrafting()`, NOT `withholds()` (4466). A row stamped with the
     * old disclosure version consented to the comment being *read and
     * summarised*; this path writes a message the business may send back to the
     * reviewer, which those words never described. The narrower gate is the
     * whole of the fix — never widen it here.
     */
    private function phiWithheld(): bool
    {
        return $this->phiWithheld ??= app(PhiAnalysisConsent::class)
            ->withholdsDrafting($this->businessId, $this->reviewId);
    }
}
