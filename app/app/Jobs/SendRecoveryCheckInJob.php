<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AutopilotActionType;
use App\Enums\CheckInAttemptStatus;
use App\Exceptions\TextNotDeliverable;
use App\Models\TriageConversation;
use App\Services\Messaging\RecoveryCheckInSender;
use App\Services\Reviews\ReviewRouter;

/**
 * "Did we get that sorted?" — one conversation, off the sweep — T546
 * §37.3(1), wave 38 lane C (10590–10609).
 *
 * ⚠️ **`AutopilotJob` RATHER THAN A PLAIN JOB, ON `SendReviewInviteJob`'s OWN
 * ARGUMENT — VERBATIM.** This is a tenant's automation acting on their behalf,
 * so it wants the tenant established, the run row, the idempotency claim, the
 * kill switch and the activity feed.
 *
 * NO SEPARATE `handoff()` WORK, ON `SendReviewInviteJob`'s OWN REASONING.
 * Nothing here touches the Google Business Profile API — the check-in link is
 * our own signed route and the send is our own mailer/texter — so there is no
 * reduced-capability version to describe.
 *
 * ⚠️ **THE CONVERSATION IS LOADED THROUGH `ReviewRouter::findConversation()`,
 * NEVER QUERIED DIRECTLY.** `Architecture\ReviewsTest` holds the recovery
 * conversation model reachable from `ReviewRouter` alone.
 */
final class SendRecoveryCheckInJob extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $conversationId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'review.fix_then_ask.checkin';
    }

    /**
     * One check-in per conversation, ever — the layer that holds under retry,
     * on `SendReviewInviteJob::idempotencyKey()`'s own reasoning.
     */
    protected function idempotencyKey(): string
    {
        return 'fix-then-ask-checkin:'.$this->conversationId;
    }

    /**
     * ⚠️ FALSE UNTIL `checkIn()` RETURNS — `SendReviewInviteJob::$claimSpent`'s
     * own argument, restated rather than re-derived: a refused SMTP
     * connection or a queue blip must not spend the claim, or the check-in is
     * lost for ever on the first attempt.
     */
    private bool $claimSpent = false;

    /**
     * ⚠️ NARROWER THAN {@see self::$claimSpent}, ON THE SAME GAP
     * `SendReviewInviteJob::$invited` EXISTS TO CLOSE. The feed's own
     * question: did a message reach a person?
     */
    private bool $sent = false;

    protected function claimIsSpent(): bool
    {
        return $this->claimSpent;
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return $this->sent ? AutopilotActionType::RecoveryCheckInSent : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->checkIn();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->checkIn();
    }

    /**
     * @return array<string, mixed>
     */
    private function checkIn(): array
    {
        $conversation = app(ReviewRouter::class)->findConversation($this->conversationId);

        if (! $conversation instanceof TriageConversation) {
            return ['sent' => false, 'skipped' => 'conversation_missing'];
        }

        try {
            $attempt = app(RecoveryCheckInSender::class)->attempt($conversation);
        } catch (TextNotDeliverable $e) {
            // The transport can prove nothing left this machine — the same
            // condition `SendReviewInviteJob` hands its claim back for, on
            // 7067's ruling.
            $this->claimSpent = $e->mayHaveReachedCarrier;

            throw $e;
        }

        $this->claimSpent = true;
        $this->sent = $attempt->status === CheckInAttemptStatus::Sent;

        return match ($attempt->status) {
            CheckInAttemptStatus::Sent => ['sent' => true],
            CheckInAttemptStatus::Refused => [
                'sent' => false,
                'refusal' => $attempt->reason?->value,
            ],
            CheckInAttemptStatus::Duplicate => ['sent' => false, 'skipped' => 'already_offered'],
            CheckInAttemptStatus::NotAttempted => ['sent' => false, 'skipped' => 'not_attempted'],
        };
    }
}
