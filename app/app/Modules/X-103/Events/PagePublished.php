<?php

declare(strict_types=1);

namespace App\Modules\X103\Events;

final class PagePublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $pageId,
        public readonly string $commitId,
        public readonly int $versionId
    ) {}
}
