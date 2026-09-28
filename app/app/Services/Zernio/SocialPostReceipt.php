<?php

declare(strict_types=1);

namespace App\Services\Zernio;

final readonly class SocialPostReceipt
{
    /**
     * @param  array<string, SocialPlatformResult>  $platforms
     */
    public function __construct(
        public string $outcome,
        public ?string $providerPostId,
        public ?string $vendorStatus,
        public array $platforms,
        public ?string $existingPostId,
    ) {}
}
