<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\TrialGrantRefusal;
use App\Support\Trials\TrialGrantAuthorization;
use RuntimeException;

/**
 * Thrown when the no-card trial's SMS allowance may not be minted for an account.
 *
 * ⚠️ **AN EXCEPTION RATHER THAN A FALSE, DELIBERATELY.** The caller this is
 * written for does not exist yet — nothing in `app/` constructs `CreditKind::Grant`
 * and a lint fails the build on anything that does (3119) — so the whole design
 * question is what happens when somebody builds it and forgets this check. A
 * boolean invites `if (…) { }` with an empty body and a green suite; a
 * constructor that throws on an ineligible verdict cannot be forgotten by a
 * caller who has to hold the authorization to call at all. See
 * {@see TrialGrantAuthorization}.
 *
 * ⛔ **IT IS NOT A SEND REFUSAL AND MUST NEVER BE CAUGHT AS ONE.** Withholding a
 * grant leaves the balance where it was; the send path's own refusal
 * (`CreditMovementRefused`, via `CreditLedger::record()`) is what stops a message
 * going out with no credit behind it, and that behaviour is unchanged by anything
 * in this slice. Catching this to allow a send would invert both.
 *
 * ⚠️ **IF THE GRANT LANE CALLS `authorize()` FROM A QUEUED JOB, CATCH THIS AND
 * FAIL THE JOB RATHER THAN LETTING IT PROPAGATE** (3239). Every refusal here is
 * **deterministic** — no confirmed listing, a listing somebody else claimed
 * first, a burst of accounts from one network — so not one of them is a
 * condition a retry can improve. Left to propagate it retries with backoff until
 * it exhausts its attempts and lands in `failed_jobs`, which reads as an
 * infrastructure fault, buries the actual reason under a stack trace, and raises
 * an operational alert about a decision the system made correctly. This note is
 * written for a caller that does not exist yet, and it is here because that
 * caller will not be looking for it.
 *
 * The message is written for a developer or an operator, never a customer —
 * `CreditMovementRefused`' rule, and the reasons name internal fraud signals.
 */
final class TrialGrantRefused extends RuntimeException
{
    /**
     * @param  non-empty-list<TrialGrantRefusal>  $refusals
     */
    private function __construct(string $message, public readonly array $refusals)
    {
        parent::__construct($message);
    }

    /**
     * @param  non-empty-list<TrialGrantRefusal>  $refusals
     */
    public static function for(int $businessId, array $refusals): self
    {
        $reasons = implode(', ', array_map(
            static fn (TrialGrantRefusal $refusal): string => $refusal->value,
            $refusals,
        ));

        return new self(
            "Business {$businessId} may not be granted the trial allowance: {$reasons}. "
            .'See App\Services\Billing\TrialEligibility and decisions 2066, 3117, 3220-3239.',
            $refusals,
        );
    }
}
