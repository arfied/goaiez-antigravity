<?php

declare(strict_types=1);

namespace App\Modules\X140\Events;

final class ContentCreated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $topicId,
        public readonly string $slug,
        public readonly bool $isPublished
    ) {}
}
