<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;

/**
 * Vectors, or the reason there are none.
 *
 * TWO OUTCOMES HERE, NOT AiResponse's THREE. A model cannot *decline* to embed —
 * there is no classifier on the embeddings endpoint and no `stop_reason` to
 * read — so the refusal case that AiResponse exists to make un-missable has no
 * counterpart. Recording that absence is worth more than mirroring the shape:
 * the next person to widen this will otherwise add a `refused()` nobody can ever
 * produce, and a state that cannot occur is a state nobody tests.
 *
 * OUTPUT TOKENS ARE ALWAYS ZERO and that is deliberate rather than unfilled.
 * An embeddings response reports `usage.prompt_tokens` and nothing else, because
 * nothing was generated — so the ledger row for one of these carries a real
 * input count and a true zero, not a missing figure.
 */
final readonly class EmbeddingResponse
{
    /**
     * @param  list<list<float>>  $vectors  One vector per input, in request order.
     */
    private function __construct(
        public AiModel $model,
        public array $vectors = [],
        public int $inputTokens = 0,
        public ?string $failureReason = null,
    ) {}

    /**
     * ⚠️ **`$inputTokens` IS THE PROVIDER'S NUMBER AND IS CLAMPED**, for
     * {@see AiResponse::answered()}'s reason: it is read as
     * `(int) data_get($payload, 'usage.prompt_tokens', 0)` in
     * `OpenAiEmbeddingClient`, and a `(int)` cast preserves a negative rather
     * than refusing it.
     *
     * @param  list<list<float>>  $vectors
     */
    public static function answered(AiModel $model, array $vectors, int $inputTokens): self
    {
        return new self($model, $vectors, max(0, $inputTokens));
    }

    /**
     * Something went wrong that is ours or the vendor's.
     *
     * Tokens are reported too where the vendor gave a count: a request that 500s
     * after the model has read the input can still bill for it, and a ledger
     * that drops those under-reports the bill in exactly the situation where
     * somebody is trying to work out where the money went.
     */
    public static function failed(AiModel $model, string $reason, int $inputTokens = 0): self
    {
        return new self($model, inputTokens: max(0, $inputTokens), failureReason: $reason);
    }

    public function isUsable(): bool
    {
        return $this->failureReason === null && $this->vectors !== [];
    }

    /**
     * Whether every input got a vector back.
     *
     * ⚠️ ASKED SEPARATELY FROM isUsable() BECAUSE A SHORT BATCH IS THE DANGEROUS
     * SUCCESS. A response with three vectors for four chunks looks usable, and a
     * caller zipping the two lists together by position would store chunk four's
     * text against chunk three's vector — a retrieval that returns confident,
     * wrong answers forever, with nothing anywhere to notice.
     */
    public function covers(int $expected): bool
    {
        return count($this->vectors) === $expected;
    }

    public function costInHundredthsOfCents(): int
    {
        return $this->model->costOf($this->inputTokens, 0);
    }
}
