<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Ai\EmbeddingRequest;
use App\Services\Ai\EmbeddingResponse;

/**
 * One call to one embedding model.
 *
 * SEPARATE FROM AiClient, AND THE SPLIT IS NOT COSMETIC. Widening `AiClient`
 * with an `embed()` method would oblige both of its implementations to have one,
 * and Anthropic publishes no embeddings API at all — its own documentation says
 * so and points at a third vendor. The honest shape of that is a second
 * interface with one implementation, not a first interface with a method that
 * throws on half its implementors.
 *
 * ⚠️ THE PROVIDER CLASSES ARE DELIBERATELY NOT NAMED IN THIS DOCBLOCK. The lint
 * that keeps every model call inside `AiRouter` matches the class *name*
 * anywhere in a file outside `Services/Ai`, comments included — and it is right
 * to: narrowing it to exclude comments would be a lint tuned until it stops
 * crying wolf. An earlier draft of this paragraph reddened the build.
 *
 * NEVER THROWS FOR A VENDOR REASON, inheriting AiClient's contract verbatim. An
 * unreachable host, a 500, a rate limit, a malformed body, a missing credential
 * — all come back as an EmbeddingResponse that reports what happened. The caller
 * is a queued job whose right answer to "no vectors" is to mark the source
 * failed and leave it for a retry, not to crash.
 *
 * NEVER ON A SYNCHRONOUS PATH. Same rule as AiClient (`29` §2, ≤200ms p95 from
 * materialised state), and the same build-failing test enforces it: every caller
 * reaches this through the router, which may not be named under
 * `Http/Controllers`, `Http/Middleware`, `Livewire` or `View/Components`.
 */
interface EmbeddingClient
{
    public function embed(EmbeddingRequest $request): EmbeddingResponse;
}
