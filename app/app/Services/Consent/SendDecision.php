<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\SendRefusalReason;
use App\Services\Messaging\Outbound\SendOutcome;
use LogicException;

/**
 * The answer to "may we send this", with its reason attached.
 *
 * ⚠️ ONE TRAVERSAL PRODUCES BOTH, WHICH IS THE ONLY REASON THIS TYPE IS SAFE.
 * `ConsentService::decide()` builds this, and `permit()` unwraps it — so the
 * permit and the explanation cannot disagree. A separate `whyNot()` method
 * running its own checks would be two implementations of one rule, and the
 * second would drift; `proofFor()` is one method rather than a trail alongside a
 * proof for the same reason.
 *
 * ⚠️ WHY A REASON EXISTS AT ALL. This slice adds refusals nobody has met before
 * — an unloaded scrubbing register, an undeterminable state — and both refuse
 * every marketing send. `permit()` returning a bare null gives an author in row
 * 4 nothing to act on, and the cheapest thing they can try is passing
 * `OutreachPurpose::Transactional` until it starts working. That is a silent
 * compliance regression and it is easier than reading this codebase, which is
 * why the reason has to be reachable without reading it.
 *
 * ⚠️ THE INVARIANT IS ENFORCED, NOT DOCUMENTED. Exactly one of the two is set.
 * A decision carrying both would be a permit somebody could pass to a sender
 * while an operator screen printed the refusal beside it.
 */
final readonly class SendDecision
{
    private function __construct(
        public ?SendPermit $permit,
        public ?SendRefusalReason $reason,
    ) {
        if (($permit === null) === ($reason === null)) {
            throw new LogicException(
                'A send decision is a permit or a reason, never both and never neither. '
                .'Both would let a sender hold authorisation while a screen shows the refusal.'
            );
        }
    }

    public static function granted(SendPermit $permit): self
    {
        return new self($permit, null);
    }

    public static function refused(SendRefusalReason $reason): self
    {
        return new self(null, $reason);
    }

    /**
     * Whether a permit was granted — and, for the analyser, which of the two
     * properties is therefore set.
     *
     * ⚠️ **THE ASSERTIONS RESTATE THE CONSTRUCTOR'S INVARIANT RATHER THAN
     * ADDING A SECOND RULE BESIDE IT.** Exactly one of the two is non-null and
     * the constructor throws otherwise, so a caller that re-checked `reason`
     * for null on the refused arm would be writing a branch no test could ever
     * drive red — {@see SendOutcome::wasSent()}'s
     * own note, applied to the pair one layer up. Without them, a caller that
     * wants the reason has to either re-check or reach for `permit()` and lose
     * it, and losing it is the defect this type exists to prevent.
     *
     * @phpstan-assert-if-true !null $this->permit
     *
     * @phpstan-assert-if-false !null $this->reason
     */
    public function isGranted(): bool
    {
        return $this->permit !== null;
    }
}
