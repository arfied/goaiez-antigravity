<?php

declare(strict_types=1);

namespace App\Modules\X173\Domain;

final class AccountingSyncEngine
{
    private const CONFIDENCE_THRESHOLD = 0.85;

    /**
     * Resolves accounting category from transaction description.
     * Category below confidence threshold posts to 'uncategorised' with review flag, NEVER to a guessed code (TEST ANCHOR & G1-03).
     */
    public function inferCategory(string $description, float $inferredConfidence, string $suggestedCategory): array
    {
        if ($inferredConfidence < self::CONFIDENCE_THRESHOLD) {
            return [
                'assigned_category' => 'uncategorised',
                'confidence_score' => $inferredConfidence,
                'flagged_for_review' => true,
                'is_low_confidence' => true,
            ];
        }

        return [
            'assigned_category' => $suggestedCategory,
            'confidence_score' => $inferredConfidence,
            'flagged_for_review' => false,
            'is_low_confidence' => false,
        ];
    }
}
