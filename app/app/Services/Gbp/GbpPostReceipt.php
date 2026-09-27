<?php

declare(strict_types=1);

namespace App\Services\Gbp;

final readonly class GbpPostReceipt
{
    public function __construct(
        public ?string $providerPostId,
        public string $status,
        public ?string $platformPostId,
        public ?string $errorMessage,
    ) {}
}
