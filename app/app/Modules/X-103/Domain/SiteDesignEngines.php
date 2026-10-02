<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Enums\AiModel;

/**
 * The four AIs that can design a page, so their work can be compared side by side (the boss, 2026-10-02: "allow
 * ChatGPT, Gemini, Claude and Grok to generate websites so we can see the quality"). Each is that vendor's strong
 * current model, checked against the vendor's own model page that day. Each engine's design is kept separately on the
 * page (draft_meta.designs.<engine>), so running one never overwrites another.
 */
final class SiteDesignEngines
{
    public const ENGINES = [
        'claude' => ['label' => 'Claude', 'model' => 'anthropic-sonnet-5'],
        'chatgpt' => ['label' => 'ChatGPT', 'model' => 'openai-gpt-6.1-sol'],
        'gemini' => ['label' => 'Gemini', 'model' => 'google-gemini-3.1-pro'],
        'grok' => ['label' => 'Grok', 'model' => 'xai-grok-4.7'],
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
