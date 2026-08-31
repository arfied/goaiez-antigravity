<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\CheckInAttemptStatus;
use App\Enums\SendRefusalReason;
use App\Models\OutreachMessage;
use LogicException;

/**
 * What happened when this application tried to check in with one customer
 * whose complaint it believes it fixed — wave 38 lane C (10590–10609).
 *
 * ⚠️ **{@see InviteAttempt}'s OWN SHAPE, DELIBERATELY
 * BORROWED** — the three-state vocabulary, invariants enforced in the
 * constructor rather than documented, and `wasSent()` asserting rather than
 * each caller re-checking. A reader who knows one knows this one, and the
 * reason the two are not one class is {@see CheckInAttemptStatus}'s own
 * docblock.
 */
final readonly class CheckInAttempt
{
    private function __construct(
        public CheckInAttemptStatus $status,
        public ?OutreachMessage $message,
        public ?SendRefusalReason $reason,
    ) {
        if ($status === CheckInAttemptStatus::Refused && $reason === null) {
            throw new LogicException(
                'A refusal without its reason is the defect InviteAttempt exists to close, met '
                .'again here. ConsentService has answered with a reason since 391; this must not '
                .'lose it.'
            );
        }

        if ($status !== CheckInAttemptStatus::Refused && $reason !== null) {
            throw new LogicException(
                'An attempt that was not refused must not carry a refusal reason.'
            );
        }

        if (($status === CheckInAttemptStatus::Sent) !== ($message !== null)) {
            throw new LogicException(
                'A sent check-in is the row it wrote, and nothing else is. A message on a refused '
                .'attempt is a row the rollback was supposed to have taken.'
            );
        }
    }

    public static function sent(OutreachMessage $message): self
    {
        return new self(CheckInAttemptStatus::Sent, $message, null);
    }

    public static function refused(SendRefusalReason $reason): self
    {
        return new self(CheckInAttemptStatus::Refused, null, $reason);
    }

    public static function duplicate(): self
    {
        return new self(CheckInAttemptStatus::Duplicate, null, null);
    }

    public static function notAttempted(): self
    {
        return new self(CheckInAttemptStatus::NotAttempted, null, null);
    }

    /**
     * @phpstan-assert-if-true !null $this->message
     */
    public function wasSent(): bool
    {
        return $this->status === CheckInAttemptStatus::Sent;
    }
}
