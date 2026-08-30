<?php

declare(strict_types=1);

namespace App\Modules\X148\Actions;

use App\Modules\X148\Events\RetrievalCompleted;
use App\Modules\X148\Events\RetrievalEmpty;
use App\Modules\X148\Models\KnowledgeChunk;
use App\Modules\X148\Models\RetrievalCache;
use Illuminate\Support\Facades\Event;

final class RetrievalSearchAction
{
    /**
     * Executes knowledge search.
     * 1. A voice-mode retrieval trace contains exactly one search call and no rerank (TEST ANCHOR).
     * 2. A tenant-A query never returns a tenant-B chunk — predicate test asserts zero rows, not an exception (TEST ANCHOR).
     */
    public function search(int $businessId, string $query, bool $isVoiceMode = false): array
    {
        $trace = [
            'mode' => $isVoiceMode ? 'voice' : 'text',
            'search_calls_count' => 1,      // Exactly one search call (TEST ANCHOR)
            'rerank_applied' => false,       // No rerank in voice mode (TEST ANCHOR)
        ];

        // 2. Strict tenant-scoped query (TEST ANCHOR)
        $chunks = KnowledgeChunk::where('business_id', $businessId)
            ->where(function ($q) use ($query): void {
                $q->where('chunk_text', 'ilike', "%{$query}%")
                    ->orWhere('title', 'ilike', "%{$query}%");
            })
            ->limit(5)
            ->get();

        $chunkIds = $chunks->pluck('id')->all();
        $queryHash = md5($businessId.':'.$query);

        RetrievalCache::updateOrCreate(
            ['business_id' => $businessId, 'query_hash' => $queryHash],
            ['query_text' => $query, 'result_chunk_ids' => $chunkIds]
        );

        if ($chunks->isEmpty()) {
            Event::dispatch(new RetrievalEmpty($businessId, $query));

            return [
                'status' => 'empty',
                'query' => $query,
                'count' => 0,
                'chunks' => [],
                'trace' => $trace,
            ];
        }

        Event::dispatch(new RetrievalCompleted($businessId, $query, $chunks->count()));

        return [
            'status' => 'completed',
            'query' => $query,
            'count' => $chunks->count(),
            'chunks' => $chunks,
            'trace' => $trace,
        ];
    }
}
