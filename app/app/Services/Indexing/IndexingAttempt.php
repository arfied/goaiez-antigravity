<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Enums\IndexingStatus;

/**
 * What one announcement of one URL to one engine did — the value a submitter
 * hands back and {@see Indexing} turns into a row.
 *
 * ⚠️ **A REFUSAL IS A RETURN VALUE**, on `CmsAdapter`'s and `PublishOutcome`'s
 * rule. Every outcome here is ordinary: a key file that does not exist yet, a
 * sitemap line already in place, an engine asking us to slow down. None of them
 * is a reason to fail a queued job and burn a retry, and none of them is an
 * exception.
 *
 * ⛔ **`reason` IS NON-NULL EXACTLY WHEN `status` IS {@see IndexingStatus::Refused},
 * AND THE CONSTRUCTORS ARE WHAT MAKE THAT TRUE** rather than a rule somebody
 * remembers. The database carries the weaker half of the same invariant — a
 * submitted row cannot also carry a refusal — because expressing the full
 * biconditional in a CHECK would mean writing status strings into SQL, which is
 * a second source of truth for a PHP backed enum.
 */
final readonly class IndexingAttempt
{
    /**
     * @param  ?array<string, mixed>  $response  What the engine said, or what we
     *                                           observed. Never a credential and
     *                                           never a key.
     */
    private function __construct(
        public IndexingEngine $engine,
        public IndexingMethod $method,
        public IndexingStatus $status,
        public ?IndexingRefusal $reason = null,
        public ?array $response = null,
    ) {}

    public static function refused(
        IndexingEngine $engine,
        IndexingMethod $method,
        IndexingRefusal $reason,
    ): self {
        return new self($engine, $method, IndexingStatus::Refused, $reason);
    }

    /**
     * The route is already open and we did nothing — see
     * {@see IndexingStatus::InPlace}.
     *
     * @param  array<string, mixed>  $response
     */
    public static function inPlace(IndexingEngine $engine, IndexingMethod $method, array $response): self
    {
        return new self($engine, $method, IndexingStatus::InPlace, null, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function submitted(IndexingEngine $engine, IndexingMethod $method, array $response): self
    {
        return new self($engine, $method, IndexingStatus::Submitted, null, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function pending(IndexingEngine $engine, IndexingMethod $method, array $response): self
    {
        return new self($engine, $method, IndexingStatus::Pending, null, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function rejected(IndexingEngine $engine, IndexingMethod $method, array $response): self
    {
        return new self($engine, $method, IndexingStatus::Rejected, null, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function failed(IndexingEngine $engine, IndexingMethod $method, array $response): self
    {
        return new self($engine, $method, IndexingStatus::Failed, null, $response);
    }
}
