<?php

declare(strict_types=1);

namespace App\Modules\X132\Actions;

use App\Modules\X132\Events\PersonMerged;
use App\Modules\X132\Models\PersonLink;
use App\Modules\X132\Models\ResolutionEvidence;
use Illuminate\Support\Facades\Event;

final class PersonMergeAction
{
    public function merge(int $businessId, int $canonicalPersonId, int $duplicatePersonId): array
    {
        // Link duplicate to canonical
        PersonLink::updateOrCreate(
            [
                'business_id' => $businessId,
                'canonical_person_id' => $canonicalPersonId,
                'linked_person_id' => $duplicatePersonId,
            ],
            [
                'confidence_rate' => 1.000,
                'match_tier' => 'manual_admin_merge',
            ]
        );

        // Reassign evidence rows
        ResolutionEvidence::where('business_id', $businessId)
            ->where('canonical_person_id', $duplicatePersonId)
            ->update(['canonical_person_id' => $canonicalPersonId]);

        Event::dispatch(new PersonMerged($businessId, $canonicalPersonId, $duplicatePersonId));

        return [
            'canonical_person_id' => $canonicalPersonId,
            'merged_person_id' => $duplicatePersonId,
            'status' => 'merged',
        ];
    }
}
