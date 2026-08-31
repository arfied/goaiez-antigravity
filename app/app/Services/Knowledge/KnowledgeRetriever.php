<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\AiTask;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Services\Ai\AiRouter;
use App\Services\Ai\EmbeddingRequest;

/**
 * Finds the pieces of a tenant's own documents that answer a question.
 *
 * ⚠️ **THE ONLY CALLER OF `KnowledgeChunk::nearestTo()` IN THE APPLICATION, AND
 * THAT IS THE DESIGN.** That method's own docblock asks for exactly this: *"if
 * you find yourself writing `embedding <=>` anywhere else, the answer is almost
 * always to extend this method instead."* One similarity query means one place
 * to get the tenant predicate right, and a build-failing test
 * (`TenancyTest`: *no vector similarity query runs without an explicit tenant
 * predicate*) watches every string literal in `app/` for a distance operator
 * without a `business_id` beside it.
 *
 * ⚠️ **NEVER ON A SYNCHRONOUS PATH — AND THIS ONE IS EASY TO GET WRONG.** A
 * question has to be embedded before it can be compared, so retrieval makes a
 * vendor call: this is an LLM call wearing a search box's clothes. `29` §2's
 * ≤200ms p95 context packet cannot contain one, and `ArchitectureTest` enforces
 * it structurally — this class reaches the vendor through `AiRouter`, and
 * `AiRouter` may not be named under `Http/Controllers`, `Http/Middleware`,
 * `Livewire` or `View/Components`.
 *
 * ⚠️ **RETURNS TYPED SNIPPETS, NOT ROWS.** See KnowledgeSnippet for why.
 *
 * ⛔ **THIS IS NOT THE ANSWER GATE.** Retrieval returns the nearest chunks, and
 * *nearest* is not *relevant*: with an empty corpus it returns nothing, and with
 * an unrelated corpus it returns the least-unrelated thing in it, confidently.
 * Deciding whether a snippet is good enough to answer from — and what to do when
 * it is not — belongs to the conversation lane, which owns the Brain, the turn
 * policy and the send path. Nothing here should be read as having made that
 * decision.
 */
final class KnowledgeRetriever
{
    /**
     * How many chunks a question retrieves by default.
     *
     * Small on purpose. Every snippet is prompt tokens the conversation lane
     * pays for on every turn, and a wide result set buries the one good chunk
     * among four mediocre ones rather than adding four good ones.
     */
    public const int DEFAULT_LIMIT = 5;

    public function __construct(
        private readonly AiRouter $router,
    ) {}

    /**
     * The snippets nearest to a question, within the current tenant.
     *
     * Returns an empty list rather than throwing when the question cannot be
     * embedded — a vendor outage means the Brain has no context, which is a
     * state the conversation lane must handle anyway, and is not something to
     * crash a queued job over.
     *
     * @return list<KnowledgeSnippet>
     */
    public function retrieve(string $question, int $limit = self::DEFAULT_LIMIT): array
    {
        $question = trim($question);

        if ($question === '') {
            return [];
        }

        $response = $this->router->embed(new EmbeddingRequest(
            task: AiTask::KnowledgeEmbedding,
            inputs: [$question],
        ));

        if (! $response->isUsable() || ! $response->covers(1)) {
            return [];
        }

        // The one similarity query in the application. See the class docblock.
        $chunks = KnowledgeChunk::nearestTo($response->vectors[0], $limit);

        if ($chunks->isEmpty()) {
            return [];
        }

        // Titles in one query rather than one per chunk. Scoped by the global
        // scope on KnowledgeSource, so a source id that somehow named another
        // tenant's row resolves to nothing rather than to their title.
        $titles = KnowledgeSource::query()
            ->whereIn('id', $chunks->pluck('source_id')->all())
            ->pluck('title', 'id');

        $snippets = [];

        foreach ($chunks as $chunk) {
            $title = $titles->get($chunk->source_id);

            $snippets[] = KnowledgeSnippet::fromChunk($chunk, is_string($title) ? $title : null);
        }

        return $snippets;
    }
}
