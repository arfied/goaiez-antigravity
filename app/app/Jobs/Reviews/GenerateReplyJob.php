<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Enums\AutopilotActionType;
use App\Enums\ReviewSource;
use App\Exceptions\ReplyGuardrailRefused;
use App\Jobs\AutopilotJob;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Services\Ai\AiSpend;
use App\Services\Reviews\PhiAnalysisConsent;
use App\Services\Reviews\ReplyDraft;
use App\Services\Reviews\ReplyGenerator;
use App\Services\Reviews\ReviewReplies;
use RuntimeException;

/**
 * Draft a reply to one Google review (`17` GBP-03).
 *
 * PHI tenants hand off — the same gate AnalyzeReviewJob uses (421, moved per
 * review at 2079-2081; see phiWithheld() for why that is a no-op on this path
 * and is wired anyway). Cap exhaustion hands off too, so a silent no-op cannot
 * look like success.
 *
 * ⚠️ DISPATCHED FROM GoogleReviewIngest ON INSERT ONLY when auto_reply is on
 * and the Google payload says nobody has replied yet. Re-sync must not spam.
 */
final class GenerateReplyJob extends AutopilotJob
{
    private string $handoffReason = 'unknown';

    private ?bool $phiWithheld = null;

    /** True once a suggestion was written — the claim is then earned. */
    private bool $suggestionRecorded = false;

    /** True when this review must never generate again (already replied, etc.). */
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
        return 'reviews.generate_reply';
    }

    protected function idempotencyKey(): string
    {
        return 'generate-reply:'.$this->reviewId;
    }

    protected function claimIsSpent(): bool
    {
        return $this->suggestionRecorded || $this->permanentlySkipped;
    }

    protected function activityAction(): ?AutopilotActionType
    {
        // ReviewReplies records ReplySuggested itself — the job stays plumbing.
        return null;
    }

    protected function canExecute(): bool
    {
        if ($this->phiWithheld()) {
            $this->handoffReason = 'phi_withheld';

            return false;
        }

        if (! app(AiSpend::class)->allows()) {
            $this->handoffReason = 'ai_cap_exhausted';

            return false;
        }

        $review = $this->review();

        if ($review === null) {
            $this->handoffReason = 'review_missing';
            $this->permanentlySkipped = true;

            return false;
        }

        if ($review->source !== ReviewSource::Google) {
            $this->handoffReason = 'not_google';
            $this->permanentlySkipped = true;

            return false;
        }

        if ($this->alreadyHasOwnerReply($review)) {
            $this->handoffReason = 'already_replied';
            $this->permanentlySkipped = true;

            return false;
        }

        if (app(ReviewReplies::class)->hasDecidedReply($review)) {
            // Approved counts alongside posted: regenerating over a decision
            // would discard the owner's edits and their approval silently.
            $this->handoffReason = 'already_decided';
            $this->permanentlySkipped = true;

            return false;
        }

        $settings = AutopilotSettings::query()
            ->where('location_id', $review->location_id)
            ->first();

        if ($settings !== null && ! $settings->auto_reply) {
            $this->handoffReason = 'auto_reply_off';
            $this->permanentlySkipped = true;

            return false;
        }

        return true;
    }

    protected function execute(): array
    {
        $review = $this->review();

        if ($review === null) {
            $this->permanentlySkipped = true;

            return ['outcome' => 'unavailable', 'reason' => 'review_missing'];
        }

        $location = Location::query()->find($review->location_id);
        $business = Business::query()->find($this->businessId);

        if ($location === null || $business === null) {
            return ['outcome' => 'unavailable', 'reason' => 'location_or_business_missing'];
        }

        $generator = app(ReplyGenerator::class);

        // ⚠️ DERIVED ONCE, HERE, AND NOT AGAIN INSIDE THE CATCH (1934). The
        // fallback below needs the same label `draft()` used, and re-deriving it
        // after `recordSuggestion()` has run makes the two agree only because
        // nothing in that call refreshes `$review` — a property of somebody
        // else's code, which is not a thing to depend on when the output
        // publishes under the tenant's name. A local removes the question.
        $reviewerLabel = $generator->reviewerLabel($review->reviewer_name);

        $draft = $generator->draft($review, $location, $business);
        $replies = app(ReviewReplies::class);

        // ⚠️ NEVER AUTO-PUBLISH A FALLBACK PRODUCED BY AN OUTAGE. The safe
        // template is honest copy, but the words the model would have written
        // are still unwritten — auto-approving canned text here would publish it
        // under the tenant's name and then make the retry a no-op, because
        // recordSuggestion() refuses to overwrite a decision.
        $wantsAuto = $replies->wantsAutoPost($review) && ! $draft->retryable;

        try {
            $reply = $replies->recordSuggestion($review, $draft, wantsAutoPost: $wantsAuto);
        } catch (ReplyGuardrailRefused) {
            // ⚠️ THE CHOKEPOINT'S ONE REACHABLE CASE USED TO FAIL TO TOTAL
            // SILENCE (1854). 1743 names expansion-across-a-placeholder as the
            // writer guardrail's genuine remaining value — a shop called *Gift*
            // and a model writing "enjoy your {{business_name}} card". Executed,
            // that produced **no reply row, no feed item**, an `AutomationRun`
            // with `output = null`, and a claim handed back to fail the same way
            // forever; on the sync driver it propagated through
            // `GoogleReviewIngest::upsertOne()` to a 500 and aborted the rest of
            // that page of reviews. 1682's rule is that the owner always has
            // something to approve, and the safe template is what it is for.
            $draft = ReplyDraft::fallback(
                $generator->safeTemplate(
                    rating: (int) $review->rating,
                    businessName: (string) $business->name,
                    reviewerLabel: $reviewerLabel,
                ),
                'guardrail_blocked_after_expansion',
            );

            // Not caught a second time. The safe template names only the tenant
            // — whose values `allows()` exempts — and an allowlisted reviewer
            // label, so a refusal here means the exemption set itself no longer
            // covers the platform's own copy, which is a real fault and must
            // reach the retry ladder rather than a third fallback.
            $reply = $replies->recordSuggestion($review, $draft, wantsAutoPost: $wantsAuto);
        }

        // ⚠️ THE CLAIM IS EARNED ONLY BY AN ANSWER. A transport failure decided
        // nothing, so the idempotency key is released and the exception below
        // sends the job back through `backoff()`. Without both halves the
        // release is inert: `handle()` only retries on a throw, and
        // `GoogleReviewIngest` dispatches on insert once and never again — so a
        // released claim with nothing to re-claim it is 256's vacuous gate.
        $this->suggestionRecorded = ! $draft->retryable;

        if ($draft->retryable) {
            throw new RuntimeException(
                'Reply generation fell back to the safe template ('
                .(string) $draft->fallbackReason.'); retrying.',
            );
        }

        return [
            'outcome' => 'suggested',
            'reply_id' => $reply->id,
            'auto_post_queued' => $wantsAuto,
            'from_model' => $draft->fromModel,
            'fallback_reason' => $draft->fallbackReason,
        ];
    }

    protected function handoff(): array
    {
        return [
            'outcome' => 'handoff',
            'reason' => $this->handoffReason,
        ];
    }

    private function review(): ?Review
    {
        return Review::query()->find($this->reviewId);
    }

    private function alreadyHasOwnerReply(Review $review): bool
    {
        $payload = $review->raw_payload;

        return is_array($payload) && ($payload['has_owner_reply'] ?? false) === true;
    }

    /**
     * ⚠️ PER REVIEW SINCE 2079-2081, AND IN PRACTICE THAT CHANGES NOTHING HERE
     * — WHICH IS THE POINT RATHER THAN A REASON TO SKIP IT (2939).
     *
     * This job only ever runs on a **Google** review (`canExecute()` refuses
     * anything else with `not_google`), and a Google review's author never saw
     * our feedback page, never saw the undertaking, and can therefore never have
     * a `review_phi_consents` row. So for a covered entity this gate answers
     * "withhold" every single time, exactly as decision 421's per-tenant read
     * did — the behaviour is unchanged and the tests that pin it are unchanged.
     *
     * It asks through the same service as `AnalyzeReviewJob` anyway, for two
     * reasons. One reader means the ruling cannot be half-applied: a fourth copy
     * of the old read left here would be a per-tenant withhold sitting beside a
     * per-review one, and whoever next reconciled them would have to guess which
     * was current. And the day a first-party review reaches this path — the
     * reply drafter is not conceptually Google-only, only `canExecute()` is — it
     * inherits the correct answer instead of a stale one.
     */
    private function phiWithheld(): bool
    {
        return $this->phiWithheld ??= app(PhiAnalysisConsent::class)
            ->withholds($this->businessId, $this->reviewId);
    }
}
