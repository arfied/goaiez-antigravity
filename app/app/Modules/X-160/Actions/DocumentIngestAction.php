<?php

declare(strict_types=1);

namespace App\Modules\X160\Actions;

use App\Modules\X160\Domain\DocumentExtractionEngine;

final class DocumentIngestAction
{
    public function __construct(private readonly DocumentExtractionEngine $engine = new DocumentExtractionEngine) {}

    public function handle(int $businessId, string $title, string $content, string $mimeType = 'application/pdf'): array
    {
        return $this->engine->ingest($businessId, $title, $content, $mimeType);
    }
}
