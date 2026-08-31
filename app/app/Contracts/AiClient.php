<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Ai\AiRequest;
use App\Services\Ai\AiResponse;

/**
 * One call to one model.
 *
 * The seam `CLAUDE.md`'s "multi-provider from day one" requires, and the reason
 * the second implementation exists before anything needs it: a router with one
 * provider wired cannot honour a setting that names the other, and the shape of
 * the abstraction is only tested by a second implementation living inside it.
 *
 * NEVER THROWS FOR A VENDOR REASON. Every failure a provider can produce — an
 * unreachable host, a 500, a rate limit, a malformed body, a refusal, an
 * exhausted budget — comes back as an AiResponse that reports what happened.
 * This mirrors PlacesClient's contract and exists for the same reason: the
 * callers are queued jobs whose right response to "the vendor said no" is to
 * record it and move on, not to crash and retry a bill.
 *
 * NEVER ON A SYNCHRONOUS PATH. `29` §2 forbids an LLM call on the synchronous
 * context path, which must serve in under 200ms p95 from materialised state.
 * Every implementation of this interface is slow by nature, and every caller
 * must be a queued job — ArchitectureTest asserts it rather than trusting it.
 */
interface AiClient
{
    public function complete(AiRequest $request): AiResponse;
}
