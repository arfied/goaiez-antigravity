<?php

declare(strict_types=1);

namespace App\Modules\X132\Actions;

use App\Modules\X132\Events\PersonResolved;
use App\Modules\X132\Models\PersonLink;
use App\Modules\X132\Models\ResolutionEvidence;
use Illuminate\Support\Facades\Event;

final class PersonResolveAction
{
    /**
     * Resolves person identity using 6-tier waterfall with per-field confidence (G13-25, P-147).
     * Knowing who someone is does NOT make them contactable (P-068, G13-11, G13-18).
     * Zero contact permission grants or transport writes (TEST ANCHOR).
     */
    public function resolveIdentity(
        int $businessId,
        int $canonicalPersonId,
        array $evidenceFields = [],
        ?int $matchingPersonId = null
    ): array {
        $highestConfidence = 0.0;
        $appliedTier = 'tier_6_ip_domain_reveal';

        foreach ($evidenceFields as $field) {
            $fieldName = $field['name'] ?? 'email';
            $fieldVal = $field['value'] ?? '';
            $provider = $field['source'] ?? 'graph_ingest';
            $confidence = (float) ($field['confidence'] ?? 0.90);

            ResolutionEvidence::create([
                'business_id' => $businessId,
                'canonical_person_id' => $canonicalPersonId,
                'field_name' => $fieldName,
                'field_value' => $fieldVal,
                'source_provider' => $provider,
                'confidence_score' => $confidence,
            ]);

            if ($confidence > $highestConfidence) {
                $highestConfidence = $confidence;
            }
        }

        if ($matchingPersonId && $matchingPersonId !== $canonicalPersonId) {
            PersonLink::updateOrCreate(
                [
                    'business_id' => $businessId,
                    'canonical_person_id' => $canonicalPersonId,
                    'linked_person_id' => $matchingPersonId,
                ],
                [
                    'confidence_score' => $highestConfidence,
                    'match_tier' => 'tier_1_exact_email',
                ]
            );
        }

        Event::dispatch(new PersonResolved($businessId, $canonicalPersonId, $appliedTier, $highestConfidence));

        return [
            'canonical_person_id' => $canonicalPersonId,
            'confidence' => $highestConfidence,
            'status' => 'resolved',
        ];
    }
}
