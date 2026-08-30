<?php

declare(strict_types=1);

namespace App\Modules\X195\Events;

final class ManifestInstalled
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $installId,
        public readonly int $marketItemId,
        public readonly string $version
    ) {}
}
