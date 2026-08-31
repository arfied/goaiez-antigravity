<?php

declare(strict_types=1);

namespace App\Support\Trials;

use App\Enums\TrialGrantRefusal;
use App\Services\Billing\TrialEligibility;

/**
 * What the abuse controls found about one account, and nothing about what to do.
 *
 * ⚠️ **IT CAN ONLY EVER WITHHOLD.** There is no method here that permits
 * anything: `isEligible()` means "no control objected", not "this account is
 * entitled". The distinction is the reason `PlanCharges`, `CreditLedger` and
 * every consent check are untouched by this slice — an empty refusal list adds
 * no permission that was not already there.
 *
 * ⚠️ **EVERY REASON IS CARRIED, NOT THE FIRST ONE.** A verdict that short-circuits
 * on the first refusal tells a support agent to fix one thing, and when they fix
 * it the account is refused again for the next — which is the same conversation
 * three times. It also matters for the record: "this account has no confirmed
 * listing *and* arrived in a burst of nine" is a different finding from either
 * half.
 *
 * Built only by {@see TrialEligibility}.
 */
final readonly class TrialGrantVerdict
{
    /**
     * @param  list<TrialGrantRefusal>  $refusals
     */
    public function __construct(
        public int $businessId,
        public array $refusals,
    ) {}

    public function isEligible(): bool
    {
        return $this->refusals === [];
    }

    public function refusedBecause(TrialGrantRefusal $refusal): bool
    {
        return in_array($refusal, $this->refusals, true);
    }

    /**
     * The reasons as their string values, for a log line or a screen.
     *
     * @return list<string>
     */
    public function reasons(): array
    {
        return array_map(
            static fn (TrialGrantRefusal $refusal): string => $refusal->value,
            $this->refusals,
        );
    }
}
