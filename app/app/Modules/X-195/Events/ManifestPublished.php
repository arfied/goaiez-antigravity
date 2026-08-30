<?php

declare(strict_types=1);

namespace App\Modules\X195\Events;

final class ManifestPublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $marketItemId,
        public readonly string $itemSlug,
        public readonly string $version
    ) {}
}
