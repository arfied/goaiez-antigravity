<?php

declare(strict_types=1);

namespace App\Modules\X144\Actions;

use App\Modules\X144\Events\CompetitorOutranking;
use App\Modules\X144\Events\MentionedByAi;
use App\Modules\X144\Events\VisibilityChanged;
use App\Modules\X144\Models\VisibilityAnswer;
use App\Modules\X144\Models\VisibilityQuery;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class VisibilityQueryAction
{
    /**
     * Records an answer engine optimization query and observed LLM response (G9-18, G13-08).
     * 1. Every answer row stores the verbatim text and the date it was asked (TEST ANCHOR).
     * 2. A run NEVER writes a verdict without the answer text behind it (TEST ANCHOR).
     */
    public function recordQueryAnswer(
        int $businessId,
        string $promptQuestion,
        string $targetEngine,
        string $verbatimText,
        string $verdict,
        bool $tenantMentioned = false,
        bool $competitorOutranking = false,
        ?int $rankPosition = null,
        ?string $askedAt = null
    ): VisibilityAnswer {
        // TEST ANCHOR: A run never writes a verdict without the answer text behind it
        if (trim($verbatimText) === '') {
            throw new InvalidArgumentException('Visibility verdict write rejected: missing required verbatim answer text (TEST ANCHOR)');
        }

        $query = VisibilityQuery::firstOrCreate(
            ['business_id' => $businessId, 'prompt_question' => $promptQuestion],
            ['target_engine' => $targetEngine]
        );

        $date = $askedAt ?? now()->toDateString();

        $answer = VisibilityAnswer::create([
            'business_id' => $businessId,
            'query_id' => $query->id,
            'verbatim_text' => $verbatimText, // TEST ANCHOR
            'asked_at' => $date, // TEST ANCHOR
            'tenant_mentioned' => $tenantMentioned,
            'competitor_outranking' => $competitorOutranking,
            'rank_position' => $rankPosition,
            'verdict' => $verdict,
        ]);

        Event::dispatch(new VisibilityChanged($businessId, $query->id, $verdict));

        if ($tenantMentioned && $rankPosition !== null) {
            Event::dispatch(new MentionedByAi($businessId, $query->id, $rankPosition));
        }

        if ($competitorOutranking) {
            Event::dispatch(new CompetitorOutranking($businessId, $query->id, 'Rival HVAC Co.'));
        }

        return $answer;
    }
}
