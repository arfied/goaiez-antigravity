<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What the application asks an AI to do, and which model answers by default.
 *
 * THIS IS THE "CONFIGURATION, NOT CODE" HALF. `CLAUDE.md` requires an
 * "admin-editable model router, multi-provider from day one … chosen per task
 * tier in admin config". The tiers are code, because each one is a distinct job
 * with distinct correctness stakes; the model serving a tier is a
 * `platform_settings` row, so it moves without a deploy — the same shape as the
 * daily audit budget of decision 193.
 *
 * WHY THE DEFAULTS ARE SPLIT THE WAY THEY ARE (decision 278). Costed at ~400
 * input and ~120 output tokens per call and 30 reviews per tenant per month,
 * every option lands between $0.008 and $0.30 per tenant per month, against a
 * $50/month cap (decision 150). **The cost difference is not a real
 * consideration**, so the split is drawn on consequence instead:
 *
 *   Analysis and moderation are internal plumbing. A wrong sentiment routes a
 *   happy customer to triage or an unhappy one to a Google invite — bad, but
 *   recoverable, and classification is what the cheap tier is good at.
 *
 *   A reply is **published under the business's own name on their listing**.
 *   Nobody sees our prompt; everybody sees the output. That is worth the dearest
 *   model available, and the delta is about 24 cents per tenant per month.
 */
enum AiTask: string
{
    /** FPR-02: sentiment, themes and moderation flags from one first-party review. */
    case ReviewAnalysis = 'review_analysis';

    /** FPR-02: does this text need withholding from display? */
    case Moderation = 'moderation';

    case SiteCopy = 'site_copy';

    /**
     * the two owner-facing authoring surfaces (a page from a sentence, an edit to an existing page), split from SiteCopy so the model can be raised for them alone.
     */
    case SiteAuthoring = 'site_authoring';

    /** GBP-03: a reply to a review, in the tenant's brand voice, published publicly. */
    case ReplyGeneration = 'reply_generation';

    /**
     * The Brain's content half: a tenant's own document, turned into vectors.
     *
     * A TIER RATHER THAN A HARDCODED MODEL, for the same reason as the three
     * above — an embedding model is retired or repriced like any other, and the
     * operator has to be able to move it without a deploy. ⚠️ **Moving it is not
     * free the way moving a completion tier is**: every chunk already stored was
     * embedded by the old model, and vectors from two models are not comparable,
     * so a change here means re-embedding the corpus before retrieval means
     * anything. The dimension is fixed at the column (1536), which is the
     * backstop — see AiModel::embeddingDimensions().
     */
    case KnowledgeEmbedding = 'knowledge_embedding';

    /**
     * T176 §2: one turn of the SMS front desk, written to a member of the public
     * under the business's name.
     *
     * ⚠️ **SONNET 5 RATHER THAN OPUS 5, AND THE ARGUMENT IS THE GRANT AND NOT THE
     * STAKES** (decision 4136). By consequence this belongs beside
     * `ReplyGeneration`: both are published under a tenant's own name to somebody
     * who never sees our prompt, which is the split this enum's own docblock
     * draws. What moves it down a tier is arithmetic the review path does not
     * have to do. Costed on this file's verified prices at roughly 2,000 input
     * and 200 output tokens per turn — a briefing, the thread, and one SMS out:
     *
     *   Opus 5    $5/$25  → ~1.5¢ a turn → 500 turns is **$7.50** of our cost
     *   Sonnet 5  $3/$15  → ~0.9¢ a turn → 500 turns is **$4.30**
     *   Haiku 4.5 $1/$5   → ~0.3¢ a turn → 500 turns is **$1.50**
     *
     * The monthly grant is 500 SMS and $50 of *retail* AI credit (9180, reversing
     * 3412's $30), which at 3412's unchanged 8:1 markup is about **$6.25 of real
     * spend**. Opus 5 would put a tenant who used their texts on conversation
     * alone **over** their whole AI grant before a single review was analysed;
     * Sonnet 5 lands at roughly two thirds of it. ⚠️ **Haiku is the cheap tier
     * this enum reserves for classification**, and this is not classification —
     * it is prose sent to a stranger that may quote a price, so the floor is set
     * above it deliberately.
     *
     * ⛔ **9180 WEAKENED THIS ARGUMENT WITHOUT CHANGING ITS ANSWER, AND THAT IS
     * WORTH SAYING RATHER THAN QUIETLY RESTATING.** At $30 the sentence above
     * read *"at twice their whole AI grant"*; at $50 it is about a fifth over.
     * The tier still holds — Opus still overruns the grant on texts alone — but
     * the margin that decided 4136 has narrowed from 2× to 1.2×, so **the next
     * move of this grant or of Opus's price is the one that flips it**, and
     * whoever makes it owes this paragraph a re-read rather than an edit.
     *
     * ⚠️ **AND IT IS A TIER, SO THE FIGURE ABOVE IS A DEFAULT AND NOT A RULING.**
     * `ai.model.conversation` moves it without a deploy, which is what this whole
     * enum is for; the arithmetic is recorded so the next person to move it knows
     * what they are trading.
     */
    case Conversation = 'conversation';

    case SiteImage = 'site_image';

    /**
     * The AI designer: a whole page — layout, styling and words — written as HTML from the page's content, the owner's
     * facts and nearby businesses' sites (prototype, 2026-10-02). The largest output of any tier.
     */
    case SiteDesign = 'site_design';

    /**
     * A neutral checklist of what the top local businesses cover on their websites, made from their pages so the AI
     * designer never reads their own sentences (the boss, 2026-10-02: "Grok summarising competitors").
     */
    case CompetitorDigest = 'competitor_digest';

    /**
     * The `platform_settings` key an operator overrides to move this tier.
     */
    public function settingKey(): string
    {
        return 'ai.model.'.$this->value;
    }

    /**
     * The model that serves this tier unless a setting says otherwise.
     */
    public function defaultModel(): AiModel
    {
        return match ($this) {
            self::ReviewAnalysis, self::Moderation,
            self::ReplyGeneration,
            self::Conversation => AiModel::Gpt4oMini,
            self::SiteCopy => AiModel::Gpt4oMini,
            // The authoring tier stays on the cheap model until an Anthropic key exists.
            // OWNER RULING 2026-09-29: "Gpt4oMini now, Opus when the key lands."
            // This pin is the ONLY lever. AiSpend::modelFor() documents three rungs — a tenant
            // assignment, an ai.model.<task> platform row, then this default — and only this one is
            // reachable: Livewire\Advanced\Settings renders the effective model with no save method,
            // nothing in the repository writes an ai.model.* row, and X-219's ModelResolveAction has
            // no callers. So there is no admin flip; changing the tier means changing this line.
            // Flipping it to ClaudeOpus5 also requires, in the same wave: an ANTHROPIC_API_KEY on the
            // box, and api.anthropic.com/* added to every Http::fake array under
            // tests/Modules/X-103 that exercises an authoring path. Without the second, 17 tests
            // dispatch real requests and get 401 — measured on 2026-09-29.
            self::SiteAuthoring => AiModel::Gpt4oMini,
            self::KnowledgeEmbedding => AiModel::TextEmbedding3Small,
            self::SiteImage => AiModel::GptImage25Flare,
            // The boss, 2026-10-02: "the default designer & copywriter: claude-haiku-4-5" — low cost, good taste.
            self::SiteDesign => AiModel::ClaudeHaiku45,
            // The boss, 2026-10-02: "Grok summarising competitors" — a short summary on a low-cost model.
            self::CompetitorDigest => AiModel::Grok43,
        };
    }

    /**
     * Whether this tier asks for a vector rather than for text.
     *
     * ⚠️ THE TWO KINDS ARE NOT INTERCHANGEABLE AND THE FAILURE IS SILENT AT THE
     * SEAM. `AiSpend::modelFor()` reads a `platform_settings` row, so an operator
     * can point any tier at any model in AiModel — and pointing a completion tier
     * at an embedding model produces a 404 on a chat endpoint, while pointing
     * this one at Opus 5 produces a *200* on the embeddings endpoint only if the
     * vendor happens to accept it. Neither is a mistake a caller can see. So the
     * kind is a property of the tier here, of the model in AiModel::isEmbedding(),
     * and `modelFor()` refuses to hand back a mismatch.
     */
    public function producesEmbedding(): bool
    {
        return $this === self::KnowledgeEmbedding;
    }

    public function producesImage(): bool
    {
        return $this === self::SiteImage;
    }

    /**
     * Output ceiling for this tier, in tokens.
     *
     * Sized per tier rather than globally, and the reply figure is deliberately
     * generous for a two-sentence answer: on Claude Opus 5 `max_tokens` bounds
     * thinking **and** response text together, so a budget sized for the visible
     * output alone truncates the visible output (AiModel::thinksByDefault).
     */
    public function maxOutputTokens(): int
    {
        return match ($this) {
            self::Moderation => 512,
            self::ReviewAnalysis => 1024,
            self::SiteCopy => 2048,
            // ⚠️ **2× THE TWO-SENTENCE TIER (4096).** SiteAuthoring returns a whole blocks
            // array plus an explanation — the largest visible output of any tier. 4096 is
            // argued from the ReplyGeneration precedent (4096 for two sentences), not computed,
            // because real multi-section pages are far larger than test fixtures.
            self::SiteAuthoring => 4096,
            self::ReplyGeneration => 4096,
            // ⚠️ **GENEROUS FOR A 160-CHARACTER MESSAGE, AND FOR THE SAME REASON
            // AS THE REPLY TIER ABOVE.** On Claude Sonnet 5 adaptive thinking is
            // on by default and `max_tokens` bounds thinking **and** the visible
            // text together, so a ceiling sized for one SMS truncates the SMS.
            // The composer's own character law is what keeps the message short;
            // this is what keeps the model from being cut off mid-thought while
            // deciding which of sixteen skills applies.
            self::Conversation => 2048,
            // An embeddings response contains no generated tokens at all, so
            // there is nothing to bound. Zero rather than an arbitrary number
            // because a plausible-looking ceiling here would be read as one that
            // does something. Nothing reads it: AiRouter::dispatch() refuses an
            // embedding tier outright, which is the guard that makes this arm
            // unreachable rather than merely unused.
            self::KnowledgeEmbedding => 0,
            self::SiteImage => 0,
            // A whole designed page, CSS included; on Sonnet 5 max_tokens bounds thinking and text together.
            self::SiteDesign => 16000,
            // A dozen short checklist lines.
            self::CompetitorDigest => 2048,
        };
    }

    /**
     * Whether this tier's output is seen by anyone outside the tenant.
     *
     * Used to decide how carefully a response is handled, and worth being a
     * property rather than a comment: it is the reason ReplyGeneration gets the
     * dearest model, and the reason its prompt must never allow the model's own
     * scaffolding into the text.
     */
    public function isPubliclyPublished(): bool
    {
        // ⚠️ **THE CONVERSATION TIER COUNTS, AND "PUBLISHED" IS THE WRONG WORD
        // FOR WHY.** An agent turn is not posted anywhere — it is texted to one
        // person. What this predicate actually asks is *does the tenant's name
        // ride on this output in front of somebody outside the tenant*, and for
        // a message signed "{Business}'s assistant" it plainly does. A `false`
        // here would say the model's own scaffolding leaking into the text is
        // survivable, and on this path it is a stranger's phone.
        return $this === self::ReplyGeneration || $this === self::Conversation;
    }
}
