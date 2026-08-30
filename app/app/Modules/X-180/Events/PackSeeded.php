<?php

declare(strict_types=1);

namespace App\Modules\X170\Events; // namespace will be App\Modules\X180\Events

namespace App\Modules\X180\Events;

final class PackSeeded
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $packId,
        public readonly string $packName,
        public readonly int $assetsCount
    ) {}
}
