<?php

declare(strict_types=1);

namespace App\Modules\X213\Events;

final class VisionCheckFlagged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $checkId,
        public readonly string $contentRef,
        public readonly array $defectFlags
    ) {}
}
