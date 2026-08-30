<?php

declare(strict_types=1);

namespace App\Modules\X214\Actions;

use App\Modules\X214\Domain\SurchargeEngine;

final class SurchargeQuoteAction
{
    public function __construct(private readonly SurchargeEngine $engine = new SurchargeEngine) {}

    public function handle(
        int $businessId,
        string $transactionId,
        int $amountCents,
        ?string $cardType = 'credit',
        ?string $bin = null,
        int $requestedRateBps = 250
    ): array {
        return $this->engine->quote($businessId, $transactionId, $amountCents, $cardType, $bin, $requestedRateBps);
    }
}
