<?php

declare(strict_types=1);

namespace App\Services\Zernio;

final readonly class SocialPlatformResult
{
    public function __construct(
        public string $platform,
        public string $status,
        public ?string $platformPostId,
        public ?string $platformPostUrl,
        public ?string $errorMessage,
        public ?string $errorCategory,
    ) {}
}
