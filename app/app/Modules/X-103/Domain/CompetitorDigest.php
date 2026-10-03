<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Enums\AiTask;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Config\DefaultsRegistry;
use App\Services\Visibility\CompetitorSiteNotes;
use Illuminate\Support\Facades\Cache;

/**
 * What the top local businesses cover on their websites, summarised by a low-cost AI into a neutral checklist before the
 * designer sees it (the boss, 2026-10-02: "Grok summarising competitors"). The designer then never reads a competitor's own
 * sentences — only topics, services and the kinds of proof they show — so it cannot echo their wording.
 *
 * One summary per business and per version of the notes, kept for a week: the four designers and the four pages of a whole
 * site share it instead of paying for it eight times. When the summary cannot be made (no key, a refusal, an empty answer)
 * the designer gets the notes WITHOUT their page text — titles, descriptions and headings only — never the raw sentences.
 */
final class CompetitorDigest
{
    public const CACHE_DAYS = 7;

    public function __construct(
        private readonly CompetitorSiteNotes $peers,
        private readonly AiRouter $router,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function block(int $businessId): string
    {
        $raw = $this->peers->referenceBlock($businessId);
        if ($raw === '') {
            return '';
        }

        $key = 'x103:competitor-digest:'.$businessId.':'.md5($raw);
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->router->dispatch(new AiRequest(
            task: AiTask::CompetitorDigest,
            prompt: $raw,
            system: $this->registry->string('sites.design.competitor_digest_prompt'),
        ));
        $summary = $response->isUsable() && is_string($response->text) ? trim($response->text) : '';
        if ($summary === '') {
            return $this->peers->referenceBlock($businessId, false);
        }

        $block = 'What the top local businesses like this one cover on their websites (a summary — REFERENCE ONLY, a checklist of '
            .'what customers here expect; never name them):'."\n".mb_substr($summary, 0, 3000);
        Cache::put($key, $block, now()->addDays(self::CACHE_DAYS));

        return $block;
    }
}
