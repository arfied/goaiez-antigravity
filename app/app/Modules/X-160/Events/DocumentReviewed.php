<?php

declare(strict_types=1);

namespace App\Modules\X160\Events;

final class DocumentReviewed
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $documentId,
        public readonly int $extractedFactsCount
    ) {}
}
