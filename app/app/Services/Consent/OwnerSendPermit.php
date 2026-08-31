<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\CapturedBy;
use App\Enums\MessagingLane;
use App\Models\ConsentRecord;
use App\Models\OwnerNotifyNumber;
use Carbon\CarbonImmutable;

/**
 * Authorisation to text one business's own account holder, at their own
 * consented mobile number (10540, the owner ruling of 2026-08-27).
 *
 * ⛔ **NOT {@see SendPermit}, AND THAT IS THE WHOLE POINT OF A SECOND CLASS**
 * — the permit type, the lane, the suppression semantics and the audit trail
 * are all different questions for a person who is our customer rather than our
 * customer's customer. `SendPermit` is minted from a {@see ConsentRecord}
 * that names a `Customer` and carries a {@see MessagingLane} —
 * neither concept applies here. The account holder is not a customer of their
 * own business, and the owner channel is neither Lane A's shared toll-free
 * number under the "via GO AI EZ" disclosure nor a tenant's own Lane B
 * number in the sense {@see CapturedBy::lane()} means: it is a
 * third category, a platform service message about the account itself, and
 * the type system should not pretend otherwise by reusing a type built for a
 * different question.
 *
 * WHY A TYPE AND NOT A BOOLEAN, AND WHY THE CONSTRUCTOR IS PRIVATE. Exactly
 * {@see SendPermit}'s own reasoning: a required parameter of a type only
 * {@see OwnerConsentService} can mint is a line nobody can forget to write,
 * because the code does not run without it — and a forgeable permit is
 * decoration. `grant()` is the only factory, and an `ArchitectureTest` lint in
 * `tests/Feature/Architecture/OwnerChannelTest.php` confines callers of the
 * literal `OwnerSendPermit::grant` call form to `app/Services/Consent/`.
 *
 * WHY IT CARRIES THE IDENTIFIER RATHER THAN LEAVING THE SENDER TO READ ONE
 * OFF THE MODEL. {@see SendPermit}'s reason again: a sender that re-reads
 * `$ownerNotifyNumber->e164` can read a *different* number than the one this
 * permit was decided against, if the row is edited between the permit and the
 * send.
 */
final readonly class OwnerSendPermit
{
    private function __construct(
        public int $businessId,
        public string $identifier,
        public CarbonImmutable $grantedAt,
    ) {}

    /**
     * The only way a permit comes into existence.
     *
     * @internal Callers outside App\Services\Consent are a build failure — see
     *           "nothing outside the consent service mints an owner send
     *           permit" in `tests/Feature/Architecture/OwnerChannelTest.php`.
     */
    public static function grant(OwnerNotifyNumber $number): self
    {
        return new self(
            businessId: $number->business_id,
            identifier: $number->e164,
            grantedAt: CarbonImmutable::now(),
        );
    }
}
