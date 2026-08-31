<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\InviteAttemptStatus;
use App\Exceptions\TextNotDeliverable;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Services\Feedback\FeedbackPages;
use App\Services\Messaging\ReviewInviteSender;

/**
 * Invite the review a confirmed fix-then-ask just re-offered — T546 §37.3(1),
 * wave 38 lane C (10590–10609).
 *
 * ⚠️ **`SendReviewInviteJob`'s SHAPE AND ITS OWN `ReviewInviteSender::attempt()`,
 * ON `SendInviteReminderJob`'s OWN PRECEDENT FOR A SECOND OCCASION TO INVITE THE
 * SAME REVIEW.** That job already establishes the pattern this one follows: same
 * base class, same underlying sender method where it can be, its own automation
 * key and — the whole reason this class exists rather than a bare re-dispatch of
 * `SendReviewInviteJob` — its own idempotency namespace.
 *
 * ⛔ **AND THAT NAMESPACE IS NOT OPTIONAL, WHICH IS THE FINDING THIS SLICE MADE
 * AND `ReinviteDeferredReviews.php`'s OWN DOCBLOCK DID NOT ANTICIPATE.**
 * `FeedbackSubmission::store()` dispatches `SendReviewInviteJob` **unconditionally**
 * for every first-party review, "with every gate inside the job" — including
 * every review this feature exists for, whose rating never clears an invite
 * threshold. That first dispatch reaches `ReviewInviteSender::attempt()`, which
 * answers `InviteAttemptStatus::NotAttempted` (gate 2: nothing to invite them
 * to), and `SendReviewInviteJob::$claimSpent` is set the moment `attempt()`
 * returns **regardless of its status** — so `review-invite-email:{reviewId}`
 * is permanently spent for every below-threshold review before a customer ever
 * confirms a fix. Measured directly: dispatching `SendReviewInviteJob` a second
 * time for such a review, after `ReviewRouter::reoffer()` has genuinely put a
 * destination in `routed_destinations`, is refused by the run's own idempotency
 * claim and sends nothing — `AutomationRun` reads `succeeded` with
 * `{"invited":false,"skipped":"not_attempted"}`, from the **first**, stale
 * attempt's row, never re-evaluated.
 *
 * ⚠️ **WHY NOT `ReinviteDeferredReviews`' ESCAPE HATCH.** That command's later
 * dispatch works because a PAUSED tenant's `SendReviewInviteJob` is refused by
 * `AutopilotJob::handle()`'s pause check **before** `execute()`/`handoff()` ever
 * calls `attempt()` — so the claim is never reached at all during the pause, and
 * the resumed dispatch is genuinely the first one to reach it. A fix-then-ask
 * confirmation has no such pause: the review was routed normally, at submission
 * time, with nobody stopping anything.
 *
 * ⚠️ **THE COMPLIANCE LAYER IS NOT DUPLICATED, ONLY THE JOB WRAPPER IS.** This
 * calls the exact same {@see ReviewInviteSender::attempt()} `SendReviewInviteJob`
 * calls — the same five gates, the same containment, the same credit debit, the
 * same `SendKey`. What is namespaced separately is the *dispatch*, which is what
 * an idempotency key protects.
 */
final class SendConfirmedFixInviteJob extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $reviewId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'review.invite.fix_then_ask';
    }

    /**
     * One invite per confirmed fix, ever — `SendReviewInviteJob::idempotencyKey()`'s
     * own reasoning, in a namespace that cannot collide with the submission-time
     * dispatch's.
     */
    protected function idempotencyKey(): string
    {
        return 'review-invite-fix-confirmed:'.$this->reviewId;
    }

    /**
     * @see SendReviewInviteJob::$claimSpent for the argument in full —
     *      identical here, because this wraps the same {@see ReviewInviteSender::attempt()}.
     */
    private bool $claimSpent = false;

    private bool $invited = false;

    protected function claimIsSpent(): bool
    {
        return $this->claimSpent;
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return $this->invited ? AutopilotActionType::ReviewRequestSent : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->invite();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->invite();
    }

    /**
     * @return array<string, mixed>
     */
    private function invite(): array
    {
        $review = Review::query()->find($this->reviewId);

        if (! $review instanceof Review) {
            return ['invited' => false, 'skipped' => 'review_missing'];
        }

        $location = $this->location();
        $customer = $review->customer_id === null
            ? null
            : Customer::query()->find($review->customer_id);

        if (! $location instanceof Location || ! $customer instanceof Customer) {
            return ['invited' => false, 'skipped' => 'no_customer_or_location'];
        }

        $page = app(FeedbackPages::class)->forLocation($location);

        if (! $page instanceof FeedbackPage) {
            return ['invited' => false, 'skipped' => 'no_feedback_page'];
        }

        try {
            $attempt = app(ReviewInviteSender::class)->attempt($review, $location, $page, $customer);
        } catch (TextNotDeliverable $e) {
            $this->claimSpent = $e->mayHaveReachedCarrier;

            throw $e;
        }

        $this->claimSpent = true;
        $this->invited = $attempt->wasSent();

        return match ($attempt->status) {
            InviteAttemptStatus::Sent => ['invited' => true],
            InviteAttemptStatus::Refused => [
                'invited' => false,
                'refusal' => $attempt->reason?->value,
            ],
            InviteAttemptStatus::Duplicate => ['invited' => false, 'skipped' => 'already_invited'],
            InviteAttemptStatus::NotAttempted => ['invited' => false, 'skipped' => 'not_attempted'],
        };
    }
}
