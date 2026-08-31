<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\IdentifierHashEpochStatus;

/**
 * Whether this platform is sending, in the one shape a screen can render —
 * decision 9645.
 *
 * ## ⛔ Why this is one object and not three view variables
 *
 * ⛔ **THE FIRST DRAFT PASSED `readable`, `headline` AND `sentence` SEPARATELY,
 * AND A MUTATION PROVED THEY COULD DISAGREE.** Pointing one screen's
 * `readable` at a literal `true` left the headline still saying *"every send on
 * this platform is being refused"* while the panel below it withheld the remedy
 * — **an operator told the platform had stopped and given nothing to do about
 * it** — and the four-state tests stayed green throughout, because each one
 * asserted the half the other was not looking at. That is `CLAUDE.md`'s 8460
 * exactly: one rule with two copies, agreeing until one of them moved.
 *
 * ⚠️ **THE FIX IS THE SHAPE RATHER THAN A LINT OVER THE SHAPE.** A consistency
 * test would have caught that mutation and would have left the next one
 * reachable. Here there is one call, one status, and nothing a caller can hand
 * a screen that is internally inconsistent — the two sentences and the
 * predicate are all read off the same {@see IdentifierHashEpochStatus}.
 *
 * ⚠️ **THE STRINGS ARE CARRIED RATHER THAN COMPUTED HERE**, because
 * {@see IdentifierHashEpochs} is the only file permitted to know what the four
 * states mean; this is what a screen is handed, not a second opinion about it.
 */
final readonly class SuppressionReadability
{
    public function __construct(
        public IdentifierHashEpochStatus $status,
        /** One line, for a screen heading — {@see IdentifierHashEpochs::sendingHeadline()}. */
        public string $headline,
        /** The console page, whole — {@see IdentifierHashEpochs::operatorSentence()}. */
        public string $sentence,
    ) {}

    /**
     * Whether a stored identifier hash can still be compared against a fresh
     * one — and therefore whether `ConsentService::decide()` is refusing every
     * send on this platform.
     *
     * ⚠️ **THE SAME PREDICATE THE REFUSAL ITSELF ASKS**, one delegation deep,
     * so a screen cannot report sending as running while `decide()` refuses.
     */
    public function isReadable(): bool
    {
        return $this->status->isReadable();
    }
}
