<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Enums\AiModel;
use App\Services\Config\DefaultsRegistry;

/**
 * The four AIs that can design a page, so their work can be compared side by side (the boss, 2026-10-02: "allow
 * ChatGPT, Gemini, Claude and Grok to generate websites so we can see the quality"; then "try the low cost models").
 * Each is that vendor's budget tier, checked against the vendor's own model page on 2026-10-02 — roughly $0.003–0.03 a
 * page. Each is an admin setting (sites.design.engine.<engine>, Settings screen), so a model can be swapped without a
 * release; the values below are the defaults. A setting that names no text model falls back to its default. Each engine's design is kept separately on the
 * page (draft_meta.designs.<engine>), so running one never overwrites another.
 */
final class SiteDesignEngines
{
    public const ENGINES = [
        'claude' => ['label' => 'Claude', 'model' => 'anthropic-haiku-4-5'],
        'chatgpt' => ['label' => 'ChatGPT', 'model' => 'openai-gpt-6-luna'],
        'gemini' => ['label' => 'Gemini', 'model' => 'google-gemini-3.8-flash'],
        'grok' => ['label' => 'Grok', 'model' => 'xai-grok-4.3'],
    ];

    public static function model(string $engine): ?AiModel
    {
        $default = self::ENGINES[$engine]['model'] ?? null;
        if (! is_string($default)) {
            return null;
        }
        $configured = AiModel::tryFrom(app(DefaultsRegistry::class)->string('sites.design.engine.'.$engine));
        if ($configured !== null && ! $configured->isImage() && ! $configured->isEmbedding()) {
            return $configured;
        }

        return AiModel::tryFrom($default);
    }

    /** The second AI tried when the first fails or answers unusably: OpenAI's budget model, or Claude Haiku if that was the first. */
    public static function fallbackFor(AiModel $model): AiModel
    {
        return $model === AiModel::Gpt6Luna ? AiModel::ClaudeHaiku45 : AiModel::Gpt6Luna;
    }

    public static function label(string $engine): string
    {
        return self::ENGINES[$engine]['label'] ?? $engine;
    }
}
