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


    public function resolveConflict(int $businessId, int $conflictId, string $resolutionAccount): array
    {
        if (strtolower(trim($resolutionAccount)) === 'uncategorised') {
            return [
                'status' => 'refused',
                'message' => 'uncategorised is not a resolution',
            ];
        }

        $conflict = \App\Modules\X173\Models\AccountingSyncConflict::where('business_id', $businessId)->findOrFail($conflictId);

        if ($conflict->status === 'resolved') {
            return [
                'status' => 'refused',
                'message' => 'a resolved row is not overwritten',
            ];
        }

        $conflict->update([
            'assigned_category' => $resolutionAccount,
            'status' => 'resolved',
            'flagged_for_review' => false,
        ]);

        return [
            'status' => 'resolved',
            'conflict_id' => $conflict->id,
            'assigned_category' => $conflict->assigned_category,
            'message' => 'resolved',
        ];
    }
}
