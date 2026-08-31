<?php

declare(strict_types=1);

namespace App\Services\Feedback;

/**
 * One customer's answers, already validated.
 *
 * A readonly object rather than an array, so the service cannot be handed a key
 * it does not expect and Larastan can see the shape. The proof blob stays an
 * array because ConsentCapture takes one and validates its contents itself —
 * duplicating those rules here would give them two homes.
 */
final readonly class FeedbackInput
{
    /**
     * @param  array<string, mixed>  $proof  url, ip_hash, user_agent, locale.
     *                                       ConsentCapture rejects a raw IP at
     *                                       any depth and requires the first
     *                                       three on a self-rendered surface.
     */
    public function __construct(
        public int $rating,
        public ?string $comment,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public bool $smsConsent,
        public bool $emailConsent,

        // ⚠️ NOT A CONTACT CONSENT, AND ITS PLACE IN THIS LIST IS THE ONLY THING
        // IT SHARES WITH THE OTHER TWO (2079-2081). A reviewer agreeing to leave
        // health information out of their own comment permits no message of any
        // kind; it decides whether *this* review's words may reach a model.
        // Rendered only by a covered entity, and never required to submit.
        public bool $phiAnalysisConsent,
        public array $proof,
    ) {}
}
