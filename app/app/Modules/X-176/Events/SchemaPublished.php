<?php

declare(strict_types=1);

namespace App\Modules\X176\Events;

final class SchemaPublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $pageId,
        public readonly string $commitId,
        public readonly string $entityType
    ) {}
}
