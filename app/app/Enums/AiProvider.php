<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The AI vendors this application talks to.
 *
 * TWO FROM DAY ONE, BY DECISION. `CLAUDE.md`'s stack section is explicit —
 * "multi-provider from day one (OpenAI and Anthropic both wired, chosen per task
 * tier in admin config)" — and it is one of the deliberate overrides of the
 * archived `20` §5's OpenAI-default router.
 *
 * The reason is not vendor neutrality for its own sake. It is that "which model
 * serves which task" is a setting an operator changes when a price moves or a
 * model is retired, and a router with one provider wired is a router that cannot
 * honour that setting without a deploy. The second implementation existing from
 * the start is what makes the first one's seam real.
 *
 * The credential key is here rather than at the call site for the reason
 * PlatformCredentials exists: doc `38` D-149 makes `platform_credentials` the
 * runtime home for every vendor secret, and a call site reaching for
 * `config('credentials.…')` is the precedent CFG1 has to undo.
 */
enum AiProvider: string
{
    case Anthropic = 'anthropic';

    case OpenAi = 'openai';

    case Xai = 'xai';

    case Gemini = 'gemini';

    /**
     * The PlatformCredentials key holding this provider's API key.
     */
    public function credentialKey(): string
    {
        return match ($this) {
            self::Anthropic => 'anthropic_api_key',
            self::OpenAi => 'openai_api_key',
            self::Xai => 'xai_api_key',
            self::Gemini => 'gemini_api_key',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Anthropic => 'Anthropic',
            self::OpenAi => 'OpenAI',
            self::Xai => 'xAI (Grok)',
            self::Gemini => 'Google (Gemini)',
        };
    }
}
