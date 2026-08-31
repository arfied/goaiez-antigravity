<?php

declare(strict_types=1);

namespace App\Services\Reviews;

/**
 * What `ReplyGenerator::draft()` produced, and why (1729).
 *
 * ⚠️ THREE CAUSES USED TO COLLAPSE INTO ONE STRING, AND ONE OF THEM WAS AN
 * OUTAGE. `draft()` returned a plain `string`, so "the model declined", "the
 * guardrails blocked it" and "Anthropic returned a 500" reached the caller
 * identically — as safe-template text. `GenerateReplyJob` then set
 * `suggestionRecorded = true`, `claimIsSpent()` became true forever, and the
 * idempotency key was burned: **one vendor outage locked a canned template in as
 * that review's public reply, permanently, with no retry.** `29` §2 rule 40's
 * "every job retried with backoff" was defeated a layer below the job.
 *
 * ⚠️ WHICH CAUSES ARE RETRYABLE IS THE WHOLE VALUE OF THIS OBJECT, and the line
 * is drawn at *who decided*. A refusal or a guardrail block is a decision, and
 * the same prompt will get the same answer a minute later — retrying spends
 * money to reach the same template. A transport failure decided nothing; the
 * words that would have been written are still unwritten. So
 * `AiResponse::failureReason` is retryable and everything else is not.
 *
 * ⚠️ THE SAFE TEMPLATE IS STILL FILED IN EVERY CASE, INCLUDING THE RETRYABLE
 * ONE (1682 stands). A missing draft is worse than a plain thank-you: the owner
 * has nothing to approve and the feed is silent. `recordSuggestion()` replaces
 * an undecided draft rather than stacking rows, so a retry that reaches the
 * model overwrites the fallback in place.
 */
final readonly class ReplyDraft
{
    private function __construct(
        public string $text,
        public bool $fromModel,
        public ?string $fallbackReason,
        public bool $retryable,
    ) {}

    public static function fromModel(string $text): self
    {
        return new self($text, fromModel: true, fallbackReason: null, retryable: false);
    }

    /**
     * The safe template won. `$reason` names which of the three causes it was.
     */
    public static function fallback(string $text, string $reason, bool $retryable = false): self
    {
        return new self($text, fromModel: false, fallbackReason: $reason, retryable: $retryable);
    }
}
