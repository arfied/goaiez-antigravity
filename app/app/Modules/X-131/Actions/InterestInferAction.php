<?php

declare(strict_types=1);

namespace App\Modules\X131\Actions;

use App\Modules\X131\Events\InterestDetected;
use App\Modules\X131\Models\PersonInterest;
use Illuminate\Support\Facades\Event;

final class InterestInferAction
{
    /**
     * Infers an interest topic for a person.
     * 1. A tenant-set interest is NEVER overwritten by inference (TEST ANCHOR).
     * 2. Every inferred interest row carries confidence and source (TEST ANCHOR & G13-34).
     */
    public function infer(
        int $businessId,
        int $personId,
        string $topic,
        float $confidenceScore,
        string $source
    ): PersonInterest {
        $existing = PersonInterest::where('business_id', $businessId)
            ->where('person_id', $personId)
            ->where('topic', $topic)
            ->first();

        // TEST ANCHOR: A tenant-set interest is NEVER overwritten by inference
        if ($existing && $existing->is_tenant_set) {
            return $existing;
        }

        $interest = PersonInterest::updateOrCreate(
            ['business_id' => $businessId, 'person_id' => $personId, 'topic' => $topic],
            [
                'confidence_rate' => $confidenceScore,
                'source' => $source,
                'is_tenant_set' => false,
            ]
        );

        Event::dispatch(new InterestDetected($businessId, $personId, $topic, $confidenceScore, $source));

        return $interest;
    }
}
