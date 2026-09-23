<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Models\Fact;

final class SupersedeFactAction
{
    public function handle(
        int $businessId,
        string $factKey,
        string $factValue,
        string $commitId,
        string $comparisonField,
        mixed $comparisonValue
    ): array {
        $factsCreated = 0;
        $factsInvalidated = 0;

        // Check existing active fact
        $existingFact = Fact::where('business_id', $businessId)
            ->where('key', $factKey)
            ->where('is_valid', true)
            ->first();

        if ($existingFact) {
            $existingData = json_decode($existingFact->value, true);
            if ($existingData[$comparisonField] !== $comparisonValue) {
                // Invalidate old fact (TEST ANCHOR)
                $existingFact->update(['is_valid' => false]);
                $factsInvalidated++;

                // Create new fact in same commit (TEST ANCHOR)
                Fact::create([
                    'business_id' => $businessId,
                    'key' => $factKey,
                    'value' => $factValue,
                    'version' => $existingFact->version + 1,
                    'commit_id' => $commitId,
                    'is_valid' => true,
                ]);
                $factsCreated++;
            }
        } else {
            // Create fresh fact with page set and 0 embedding for numeric column (TEST ANCHOR)
            Fact::create([
                'business_id' => $businessId,
                'key' => $factKey,
                'value' => $factValue,
                'version' => 1,
                'commit_id' => $commitId,
                'is_valid' => true,
            ]);
            $factsCreated++;
        }

        return [
            'facts_created' => $factsCreated,
            'facts_invalidated' => $factsInvalidated,
        ];
    }
}
