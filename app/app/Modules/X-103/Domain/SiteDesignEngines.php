<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Enums\AiModel;

/**
 * The four AIs that can design a page, so their work can be compared side by side (the boss, 2026-10-02: "allow
 * ChatGPT, Gemini, Claude and Grok to generate websites so we can see the quality"; then "try the low cost models").
 * Each is that vendor's budget tier, checked against the vendor's own model page on 2026-10-02 — roughly $0.003–0.03 a
 * page. The stronger models stay in AiModel and can be chosen per task in admin. Each engine's design is kept separately on the
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
        $model = self::ENGINES[$engine]['model'] ?? null;

        return is_string($model) ? AiModel::tryFrom($model) : null;
    }

    public static function label(string $engine): string
    {
        return self::ENGINES[$engine]['label'] ?? $engine;
    }
}
