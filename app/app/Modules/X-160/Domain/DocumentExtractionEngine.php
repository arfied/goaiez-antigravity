<?php

declare(strict_types=1);

namespace App\Modules\X160\Domain;

use App\Modules\X121\Actions\SupersedeFactAction;
use App\Modules\X160\Events\DocumentIngested;
use App\Modules\X160\Events\DocumentReviewed;
use App\Modules\X160\Models\Document;
use App\Modules\X160\Models\DocumentVersion;
use App\Modules\X160\Models\ExtractionRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class DocumentExtractionEngine
{
    /**
     * Ingests a document with cryptographic SHA-256 (G2-30).
     */
    public function ingest(int $businessId, string $title, string $content, string $mimeType = 'application/pdf'): array
    {
        $hash = hash('sha256', $content);

        $doc = Document::create([
            'business_id' => $businessId,
            'title' => $title,
            'mime_type' => $mimeType,
            'sha256_hash' => $hash,
            'status' => 'ingested',
        ]);

        DocumentVersion::create([
            'business_id' => $businessId,
            'document_id' => $doc->id,
            'version_number' => 1,
            'file_path' => "documents/{$doc->id}/v1",
            'sha256_hash' => $hash,
        ]);

        Event::dispatch(new DocumentIngested($businessId, $doc->id, $hash));

        return [
            'status' => 'ingested',
            'document_id' => $doc->id,
            'sha256' => $hash,
        ];
    }

    /**
     * Ingests 2-column price table.
     * 1. Yields exactly one Fact per row with page set and ZERO embedding writes for numeric column (TEST ANCHOR).
     * 2. Re-uploading with 1 changed price invalidates 1 Fact and creates 1 in the same commit (TEST ANCHOR).
     */
    public function extractPriceFacts(int $businessId, int $documentId, array $twoColumnRows, int $pageNumber = 1, string $commitId = 'commit_01'): array
    {
        return DB::transaction(function () use ($businessId, $documentId, $twoColumnRows, $pageNumber, $commitId) {
            $factsCreated = 0;
            $factsInvalidated = 0;

            foreach ($twoColumnRows as $row) {
                $serviceName = $row['service'];
                $price = $row['price'];
                $factKey = "price:{$serviceName}";

                $factValue = json_encode([
                    'service' => $serviceName,
                    'price' => $price,
                    'page' => $pageNumber,
                    'numeric_embedding_vector' => null, // ZERO embedding writes for numeric column (TEST ANCHOR)
                ]);

                $result = app(SupersedeFactAction::class)->handle(
                    $businessId,
                    $factKey,
                    $factValue,
                    $commitId,
                    'price',
                    $price
                );

                $factsCreated += $result['facts_created'];
                $factsInvalidated += $result['facts_invalidated'];
            }

            ExtractionRun::create([
                'business_id' => $businessId,
                'document_id' => $documentId,
                'run_status' => 'completed',
                'extracted_facts_count' => $factsCreated,
            ]);

            Event::dispatch(new DocumentReviewed($businessId, $documentId, $factsCreated));

            return [
                'status' => 'price_facts_extracted',
                'facts_created' => $factsCreated,
                'facts_invalidated' => $factsInvalidated,
                'page' => $pageNumber,
            ];
        });
    }
}
