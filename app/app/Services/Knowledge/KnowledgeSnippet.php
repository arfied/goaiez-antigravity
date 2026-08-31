<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\KnowledgeChunk;

/**
 * One retrieved piece of a tenant's own documents.
 *
 * ⚠️ **A TYPE RATHER THAN THE ELOQUENT ROW, AND THE REASON IS THE NEXT LANE.**
 * The conversation Brain is built by somebody else and its whole job is to put
 * this text into a prompt. Handing it a `KnowledgeChunk` hands it `embedding`
 * (1536 floats nobody wants in a prompt), `metadata` (arbitrary jsonb) and a
 * live model it can `save()` — and the failure that invites is a prompt builder
 * that interpolates a model and serialises the vector into the message.
 *
 * ⚠️ **AND IT IS WHERE `business_id` STOPS.** A snippet carries no tenant key at
 * all, because nothing downstream should be making a tenant decision from a
 * value it was handed: the boundary was enforced at the query
 * (`KnowledgeChunk::nearestTo()`'s explicit predicate, plus RLS beneath it), and
 * a tenant id travelling in a DTO is an invitation to re-check it somewhere it
 * can be got wrong. The multi-tenancy skill's rule exactly — RLS catches a
 * forgotten filter, never a wrong one.
 */
final readonly class KnowledgeSnippet
{
    private function __construct(
        public int $chunkId,
        public int $sourceId,
        public string $text,
        public ?string $sourceTitle,
    ) {}

    public static function fromChunk(KnowledgeChunk $chunk, ?string $sourceTitle): self
    {
        return new self(
            chunkId: (int) $chunk->id,
            sourceId: (int) $chunk->source_id,
            text: (string) $chunk->content,
            sourceTitle: $sourceTitle,
        );
    }
}
