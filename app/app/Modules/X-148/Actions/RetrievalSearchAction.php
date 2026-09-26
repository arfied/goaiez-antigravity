<?php

declare(strict_types=1);

namespace App\Modules\X148\Actions;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Modules\X148\Events\RetrievalCompleted;
use App\Modules\X148\Events\RetrievalEmpty;
use App\Modules\X148\Models\KnowledgeChunk;
use App\Modules\X148\Models\RetrievalCache;
use App\Services\Ai\EmbeddingRequest;
use App\Services\Ai\OpenAiEmbeddingClient;
use Illuminate\Support\Facades\Event;

final class RetrievalSearchAction
{
    /**
     * Executes knowledge search using pgvector cosine distance.
     */
    public function search(int $businessId, string $query, bool $isVoiceMode = false): array
    {
        $trace = [
            'mode' => $isVoiceMode ? 'voice' : 'text',
            'search_calls_count' => 1,
            'rerank_applied' => false,
        ];

        // Fetch query embedding
        try {
            $client = new OpenAiEmbeddingClient(AiModel::TextEmbedding3Small);
            $request = new EmbeddingRequest(AiTask::ReplyGeneration, [$query]);
            $response = $client->embed($request);

            if (! $response->isUsable()) {
                throw new \Exception('Embedding failed: '.$response->failureReason);
            }

            $queryVector = $response->vectors[0];
            $vectorString = '['.implode(',', $queryVector).']';

            // Pgvector search: cosine distance <=>
            $chunks = KnowledgeChunk::where('business_id', $businessId)
                ->orderByRaw('embedding_vector <=> ?', [$vectorString])
                ->limit(5)
                ->get();

        } catch (\Exception $e) {
            // Fallback to text search if OpenAI is unavailable. The trace says so, and the
            // screen tells the owner it was a text match, not a vector search (wave 810).
            $trace['fallback'] = 'embedding_unavailable';
            $chunks = KnowledgeChunk::where('business_id', $businessId)
                ->where(function ($q) use ($query): void {
                    $q->where('chunk_text', 'ilike', "%{$query}%")
                        ->orWhere('title', 'ilike', "%{$query}%");
                })
                ->limit(5)
                ->get();
        }

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
