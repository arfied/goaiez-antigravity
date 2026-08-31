<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\AiModel;

/**
 * What came back, including the ways it can come back empty.
 *
 * THREE OUTCOMES, NOT TWO, AND THE THIRD IS THE ONE PEOPLE MISS. A model can
 * answer, it can fail, and it can **decline** — Anthropic returns a refusal as a
 * successful HTTP 200 with `stop_reason: "refusal"` and an empty or partial
 * content array. Code that reads `content[0].text` unconditionally breaks on
 * that, silently, and only on the inputs most likely to be genuinely awkward.
 *
 * Claude Opus 5 in particular runs elevated safety classifiers, and this
 * application feeds it customer-written review text — the one input nobody
 * controls. A refusal here is not an error to report; it is a review that gets
 * no AI reply and waits for a human, which is exactly what should happen.
 */
final readonly class AiResponse
{
    /**
     * @param  array<string, mixed>|null  $json  Parsed structured output, when asked for and produced.
     */
    private function __construct(
        public AiModel $model,
        public ?string $text = null,
        public ?array $json = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public bool $refused = false,
        public ?string $refusalCategory = null,
        public ?string $failureReason = null,
    ) {}

    /**
     * ⛔ **THE TOKEN COUNTS ARE THE PROVIDER'S NUMBERS AND ARE CLAMPED HERE.**
     *
     * Every client reads them straight out of somebody else's JSON —
     * `(int) data_get($payload, 'usage.input_tokens', 0)` in `AnthropicClient`,
     * `usage.prompt_tokens` / `usage.completion_tokens` in `OpenAiClient` — and
     * a `(int)` cast turns `-500` into `-500` rather than refusing it.
     *
     * ⚠️ **`ai_calls.input_tokens` IS AN `unsignedInteger` AND POSTGRES HAS NO
     * UNSIGNED INTEGER**, so that word never reached the database; the CHECK
     * constraint that now backs it is in
     * `2026_08_18_101216_constrain_unsigned_columns_that_postgres_does_not`.
     * **The clamp is here rather than being left to the constraint** because
     * `AiSpend::record()`'s own docblock is explicit that the row must survive
     * whatever happens — a refused or failed call still bills us — and a CHECK
     * that aborted the insert would lose the meter row *after* the provider had
     * already charged for it, which is the one outcome that docblock exists to
     * prevent. The constraint stands behind this for the hand-written `UPDATE`.
     *
     * ⚠️ **WHY IT MATTERS MORE THAN A WRONG COUNT.** `AiModel::costOf()`
     * multiplies these, so a negative count yields a negative
     * `cost_hundredths_cents`; `AiSpend::allows()` bounds a tenant that has
     * never been funded by `sum('cost_hundredths_cents')` against
     * `ai.monthly_cap_per_tenant`. A negative row does not merely misreport
     * spend — **it raises the effective cap by the amount it lies about.**
     *
     * @param  array<string, mixed>|null  $json
     */
    public static function answered(
        AiModel $model,
        ?string $text,
        ?array $json,
        int $inputTokens,
        int $outputTokens,
    ): self {
        return new self($model, $text, $json, max(0, $inputTokens), max(0, $outputTokens));
    }

    /**
     * The model declined. Tokens are still reported: a mid-stream refusal bills
     * for what was generated before the classifier fired, and a ledger that
     * silently drops those is a ledger that under-reports the bill.
     *
     * Clamped for {@see self::answered()}'s reason, which applies here more
     * sharply: a refusal is the arm where the provider's usage block is most
     * often partial.
     */
    public static function refused(
        AiModel $model,
        ?string $category,
        int $inputTokens = 0,
        int $outputTokens = 0,
    ): self {
        return new self(
            model: $model,
            inputTokens: max(0, $inputTokens),
            outputTokens: max(0, $outputTokens),
            refused: true,
            refusalCategory: $category,
        );
    }

    /**
     * Something went wrong that is ours or the vendor's — an unreachable host, a
     * 500, a malformed body, an exhausted budget. Never a refusal, which is the
     * system working.
     */
    public static function failed(AiModel $model, string $reason): self
    {
        return new self($model, failureReason: $reason);
    }

    /**
     * Whether there is anything here worth acting on.
     */
    public function isUsable(): bool
    {
        return ! $this->refused
            && $this->failureReason === null
            && ($this->text !== null || $this->json !== null);
    }

    public function costInHundredthsOfCents(): int
    {
        return $this->model->costOf($this->inputTokens, $this->outputTokens);
    }
}
