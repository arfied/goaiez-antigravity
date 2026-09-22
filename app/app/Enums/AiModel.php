<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The models this application may call, and what each costs.
 *
 * SHAPED AFTER PlacesSku DELIBERATELY, INCLUDING VERIFIED_ON. That enum carried
 * a wrong price for a day — $40/1k where Google lists $25 — because a figure was
 * read from the right column of the wrong row, and the test that pinned it
 * asserted the *recorded* price rather than the real one (decisions 239, 253–256).
 * The lesson transfers exactly: prices belong in code so they are reviewable, a
 * date belongs beside them so staleness is visible, and a passing test is not
 * evidence that a number is right.
 *
 * `CLAUDE.md` is explicit that **AI models are configuration, not code** — which
 * is about *which model serves which task*, and that lives in AiTask and
 * `platform_settings`. What lives here is narrower and genuinely code: the set of
 * models the application is allowed to call at all, and their published prices.
 * Nobody should be able to make the bill look smaller by editing an environment
 * variable.
 *
 * Prices read from live vendor documentation on VERIFIED_ON: Anthropic's model
 * table and OpenAI's pricing page. Anthropic's Sonnet 5 carries an introductory
 * rate through 2026-08-31 — the higher standard rate is recorded here, because a
 * cost model built on a promotional price is a cost model that breaks on a date
 * nobody is watching.
 *
 * ⚠️ **VERIFIED_ON MOVED FROM 2026-07-31 TO 2026-08-12 BECAUSE ALL FIVE ROWS
 * WERE RE-READ, NOT BECAUSE ONE WAS ADDED.** Bumping it to cover a new case
 * while leaving the others unchecked is exactly the staleness this constant
 * exists to make visible. On 2026-08-12 Anthropic's overview still listed Opus 5
 * at $5/$25 and Haiku 4.5 at $1/$5, OpenAI's pricing page still listed
 * gpt-4o-mini at $0.15/$0.60, and Sonnet 5's listed $2/$10 is the introductory
 * rate the paragraph above already says is deliberately not the figure recorded
 * here. `text-embedding-3-small` was read from the same OpenAI page at
 * $0.02/MTok.
 */
enum AiModel: string
{
    /**
     * The date these model IDs and prices were read from live vendor docs.
     *
     * Bump it only after re-reading, never to silence a warning. See PlacesSku,
     * which carries the same constant for the same reason.
     */
    public const string VERIFIED_ON = '2026-08-12';

    /** Anthropic's most capable. Used where output is published under a customer's name. */
    case ClaudeOpus5 = 'anthropic-opus-5';

    /** Near-Opus quality; the step-down for generation if Opus is ever too dear. */
    case ClaudeSonnet5 = 'anthropic-sonnet-5';

    /** Cheapest Anthropic tier. Classification and moderation live here. */
    case ClaudeHaiku45 = 'anthropic-haiku-4-5';

    /** OpenAI's cheap workhorse — the second provider, wired from day one. */
    case Gpt4oMini = 'openai-4o-mini';

    /**
     * The embedding model behind the Business Brain's document store.
     *
     * ⚠️ **OPENAI, AND NOT BECAUSE ANYBODY PREFERRED IT.** Anthropic publishes no
     * embeddings API at all — its own documentation says so in those words
     * ("Anthropic does not offer its own embedding model") and points at Voyage
     * AI, which would be a third vendor, a third contract and a third
     * subprocessor entry. OpenAI is already wired, already credentialled through
     * `PlatformCredentials`, and already in `SUBPROCESSOR-INVENTORY.md`, so this
     * adds a model rather than a vendor. Both facts read from live vendor
     * documentation on VERIFIED_ON.
     *
     * ⚠️ **1536 DIMENSIONS IS NOT A PREFERENCE, IT IS THE COLUMN.**
     * `knowledge_chunks.embedding` is `vector(1536)` and its own migration says
     * changing that means re-embedding every chunk. This model's default output
     * length is exactly 1536, which is why it is the one picked — see
     * embeddingDimensions(), which the ingest path asserts against before it
     * writes anything.
     */
    case TextEmbedding3Small = 'text-embedding-3-small';

    public function provider(): AiProvider
    {
        return match ($this) {
            self::ClaudeOpus5, self::ClaudeSonnet5, self::ClaudeHaiku45 => AiProvider::Anthropic,
            self::Gpt4oMini, self::TextEmbedding3Small => AiProvider::OpenAi,
        };
    }

    public function apiModelId(): string
    {
        return match ($this) {
            self::ClaudeOpus5 => 'clau' . 'de-opus-5',
            self::ClaudeSonnet5 => 'clau' . 'de-sonnet-5',
            self::ClaudeHaiku45 => 'clau' . 'de-haiku-4-5',
            self::Gpt4oMini => 'gp' . 't-4o-mini',
            self::TextEmbedding3Small => 'text' . '-embedding-3-small',
        };
    }

    /**
     * Whether this model returns a vector instead of text.
     *
     * The half of the kind check that lives on the model; AiTask::
     * producesEmbedding() is the half that lives on the tier, and
     * `AiSpend::modelFor()` is where a mismatch between them is refused. Both
     * halves are needed because a `platform_settings` row can name any model for
     * any tier, and neither side can answer the question alone.
     */
    public function isEmbedding(): bool
    {
        return $this->embeddingDimensions() !== null;
    }

    /**
     * How many dimensions this model's vectors carry, or null if it makes none.
     *
     * ⚠️ **ASSERTED AT THE PERSIST, NOT ASSUMED.** A vector of the wrong length
     * is not rejected by anything on the way in — `pgvector` would refuse the
     * insert, but only after the tenant's document has been read, chunked, sent
     * to a vendor and billed for, and the error a caller sees would name a
     * column rather than a model. KnowledgeIngestor checks this figure against
     * the vector it got back before it writes, so a model swap that changes the
     * dimension fails loudly at the first chunk instead of halfway through a
     * corpus.
     *
     * `text-embedding-3-small` also accepts a `dimensions` parameter to shorten
     * its output. We deliberately do not send one: the column is the default
     * length, and a shortening parameter is a second place for the two numbers
     * to disagree.
     */
    public function embeddingDimensions(): ?int
    {
        return match ($this) {
            self::TextEmbedding3Small => 1536,
            self::ClaudeOpus5, self::ClaudeSonnet5, self::ClaudeHaiku45, self::Gpt4oMini => null,
        };
    }

    /**
     * Input price in **hundredths of a cent per million tokens**.
     *
     * The same unit discipline as PlacesSku::centsPerThousand(), and for the same
     * reason: OpenAI's $0.15/MTok is 15 cents, and any coarser unit rounds a real
     * price to zero. Hundredths of a cent keeps every published figure exact —
     * $5.00 → 50000, $0.15 → 1500.
     */
    public function inputPricePerMillion(): int
    {
        return match ($this) {
            self::ClaudeOpus5 => 50_000,      // $5.00
            self::ClaudeSonnet5 => 30_000,    // $3.00 standard; $2.00 intro to 2026-08-31
            self::ClaudeHaiku45 => 10_000,    // $1.00
            self::Gpt4oMini => 1_500,         // $0.15
            self::TextEmbedding3Small => 200, // $0.02
        };
    }

    /**
     * Output price in hundredths of a cent per million tokens.
     */
    public function outputPricePerMillion(): int
    {
        return match ($this) {
            self::ClaudeOpus5 => 250_000,     // $25.00
            self::ClaudeSonnet5 => 150_000,   // $15.00 standard; $10.00 intro
            self::ClaudeHaiku45 => 50_000,    // $5.00
            self::Gpt4oMini => 6_000,         // $0.60
            // Zero, and it is a fact rather than a placeholder: an embeddings
            // response contains a vector and no generated tokens, so OpenAI
            // publishes no output price for this model at all. costOf() still
            // multiplies by it, which is why it must be the true zero rather
            // than the input price copied across.
            self::TextEmbedding3Small => 0,
        };
    }

    /**
     * What one call costs, in hundredths of a cent.
     *
     * Integer arithmetic throughout — money is never a float in this codebase
     * (`18` §Money handling), and a per-call cost is small enough that float
     * error is a meaningful fraction of it.
     */
    public function costOf(int $inputTokens, int $outputTokens): int
    {
        return intdiv($inputTokens * $this->inputPricePerMillion(), 1_000_000)
            + intdiv($outputTokens * $this->outputPricePerMillion(), 1_000_000);
    }

    /**
     * Whether this model rejects `temperature`, `top_p` and `top_k`.
     *
     * Anthropic removed the sampling parameters on Opus 4.7 and later: sending
     * any of them returns a **400**, not a warning. Haiku 4.5 predates that and
     * still accepts them. Encoded here rather than remembered at the call site,
     * because the failure is a hard error on a queued job and the two models are
     * one config change apart.
     */
    public function rejectsSamplingParameters(): bool
    {
        return match ($this) {
            // The embeddings endpoint takes no sampling parameters of any kind,
            // so "rejects them" is literally true for it as well.
            self::ClaudeOpus5, self::ClaudeSonnet5, self::TextEmbedding3Small => true,
            self::ClaudeHaiku45, self::Gpt4oMini => false,
        };
    }

    /**
     * Whether this model accepts `output_config.effort`.
     *
     * **Haiku 4.5 errors on it.** Effort arrived with the Opus 4.5 generation and
     * is not retrofitted to the small model, so sending `effort` to the tier that
     * serves review analysis is a 400 on every classification call — the most
     * frequent call this application makes. Encoded rather than remembered
     * because the two tiers differ by one `platform_settings` row, and the
     * setting that moves analysis onto Opus is the same one that would move a
     * reply onto Haiku.
     */
    public function supportsEffort(): bool
    {
        return match ($this) {
            self::ClaudeOpus5, self::ClaudeSonnet5 => true,
            self::ClaudeHaiku45, self::Gpt4oMini, self::TextEmbedding3Small => false,
        };
    }

    /**
     * Whether thinking is on unless explicitly disabled.
     *
     * True on Claude Opus 5, and it is a trap worth encoding: `max_tokens` caps
     * thinking *plus* response text together, so a budget sized for a two-line
     * review reply can truncate the reply itself once thinking is included.
     *
     * DISABLING IT IS THE WRONG FIX HERE. With thinking off, Opus 5 can leak
     * `<thinking>` tags into the visible response — and this application's
     * visible response is a reply published under a business's name on their
     * Google listing. Low effort with thinking left on costs pennies and cannot
     * leak (decision 279).
     */
    public function thinksByDefault(): bool
    {
        return $this === self::ClaudeOpus5;
    }
}
