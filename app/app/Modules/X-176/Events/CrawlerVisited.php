<?php

declare(strict_types=1);

namespace App\Modules\X176\Events;

final class CrawlerVisited
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $pageId,
        public readonly string $botUserAgent
    ) {}
}
