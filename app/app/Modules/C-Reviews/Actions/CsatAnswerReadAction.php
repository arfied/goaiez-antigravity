<?php

declare(strict_types=1);

namespace App\Modules\CReviews\Actions;

use App\Modules\CReviews\Models\CsatAnswer;

class CsatAnswerReadAction
{
    // P-113: csat_answers is 1–5; the day-60 triage branch reads 0–10, so ×2 (1–3 triages, 4–5 goes out — P-110's default threshold 4).
    public function latestNormalisedScore(int $businessId, int $personId): ?int
    {
        $answer = CsatAnswer::where('business_id', $businessId)
            ->where('person_id', $personId)
            ->where('is_valid', true)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();

        return $answer ? $answer->score * 2 : null;
    }
}
