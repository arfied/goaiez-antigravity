<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Enums\IndexingRefusal;
use InvalidArgumentException;

/**
 * A key, or the reason there is not one.
 *
 * ⚠️ **THE REASON IS AS MUCH OF THE ANSWER AS THE KEY IS.** Returning a bare
 * `?IndexNowKey` would leave the caller inventing a refusal, and the three
 * reasons a key can be missing are three different sentences to an operator —
 * *no confirmed website*, *no way to write to that host at all*, and *the plugin
 * is not built yet*. Slice E's whole instruction is that an attempt is refused
 * with a recorded reason rather than skipped, so the reason travels with the
 * absence.
 */
final readonly class IndexNowKeyOutcome
{
    private function __construct(
        public ?IndexNowKey $key,
        public ?IndexingRefusal $refusal,
    ) {}

    public static function found(IndexNowKey $key): self
    {
        return new self($key, null);
    }

    public static function missing(IndexingRefusal $refusal): self
    {
        return new self(null, $refusal);
    }

    /**
     * The refusal, for a caller that has already seen there is no key.
     *
     * @throws InvalidArgumentException When a key is present after all — a
     *                                  programming error, never a vendor
     *                                  condition.
     */
    public function refusalOrFail(): IndexingRefusal
    {
        if ($this->refusal === null) {
            throw new InvalidArgumentException('This outcome carries a key; there is no refusal to read.');
        }

        return $this->refusal;
    }
}
