<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Jobs\SummariseClosedThreadJob;

/**
 * The one line an owner is told about a finished conversation — rail 9, P13.
 *
 * ⛔ **THIS IS A CHARACTERISATION, NOT A RECORD OF WHAT WAS SAID, AND EVERY
 * CONSUMER HAS TO TREAT IT AS ONE.** A model wrote it from part of a
 * conversation; it may be wrong, it may miss the thing that mattered, and it is
 * never evidence. The conversation itself is the record, in the Inbox, and the
 * audit log carries the *fact* of the close rather than this sentence — see
 * {@see SummariseClosedThreadJob}, which writes the two books
 * deliberately differently.
 *
 * ⚠️ **{@see $fromModel} IS ON THE OBJECT SO A SCREEN CAN SAY WHICH IT IS.** The
 * static line is a statement of fact about the thread's own state — *"a long
 * conversation was handed to you"* — and the model-written one is a summary of
 * what people said. Collapsing them into one string would make the honest half
 * indistinguishable from the fallible half at exactly the moment the fallible
 * half is unavailable.
 */
final readonly class ThreadCloseSummary
{
    private function __construct(
        public string $line,
        public bool $fromModel,
        public ?string $fallbackReason = null,
    ) {}

    public static function written(string $line): self
    {
        return new self($line, true);
    }

    /**
     * The factual line, used when there is nothing to summarise or nothing to
     * summarise it with.
     *
     * ⚠️ **A REASON IS ALWAYS CARRIED**, so an operator reading a run row can
     * tell *"this business logs no message bodies"* from *"the vendor was
     * down"*. They look identical on the owner's screen and need different
     * remedies.
     */
    public static function factual(string $line, string $reason): self
    {
        return new self($line, false, $reason);
    }
}
