<?php

declare(strict_types=1);

namespace App\Modules\X148\Actions;

use App\Modules\X148\Models\KnowledgeChunk;

final class RetrievalIndexAction
{
    public function indexChunk(
        int $businessId,
        string $title,
        string $chunkText,
        ?int $documentId = null,
        ?array $embedding = null
    ): KnowledgeChunk {
        return KnowledgeChunk::create([
            'business_id' => $businessId,
            'document_id' => $documentId,
            'title' => $title,
            'chunk_text' => $chunkText,
            'embedding_vector' => $embedding,
        ]);
    }
}
