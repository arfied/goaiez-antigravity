<?php

declare(strict_types=1);

namespace App\Modules\X120\Events;

final class CardStored
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $cardTokenId,
        public readonly string $gatewayPaymentMethodId
    ) {}
}
