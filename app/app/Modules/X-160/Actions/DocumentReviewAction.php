<?php

declare(strict_types=1);

namespace App\Modules\X160\Actions;

use App\Modules\X160\Domain\DocumentExtractionEngine;

final class DocumentReviewAction
{
    public function __construct(private readonly DocumentExtractionEngine $engine = new DocumentExtractionEngine) {}

    public function handle(int $businessId, int $documentId, array $twoColumnRows, int $pageNumber = 1, string $commitId = 'commit_01'): array
    {
        return $this->engine->extractPriceFacts($businessId, $documentId, $twoColumnRows, $pageNumber, $commitId);
    }
}
