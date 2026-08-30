<?php

declare(strict_types=1);

namespace App\Modules\X16\Actions;

use App\Modules\X121\Models\Fact;
use App\Modules\X16\Models\PlacesRecord;
use Illuminate\Support\Facades\DB;

final class MapsHarvestAction
{
    /**
     * Ingests harvested places (filtering out chains: G7-21).
     */
    public function harvestPlaces(int $businessId, array $places): array
    {
        $saved = [];
        $chainsFiltered = 0;

        foreach ($places as $p) {
            $isChain = $p['is_chain'] ?? false;

            if ($isChain) {
                // Chains filtered out of prospect set (G7-21)
                $chainsFiltered++;

                continue;
            }

            $record = PlacesRecord::updateOrCreate(
                ['business_id' => $businessId, 'place_id' => $p['place_id']],
                [
                    'name' => $p['name'],
                    'address' => $p['address'],
                    'phone' => $p['phone'] ?? null,
                    'latitude' => $p['latitude'] ?? null,
                    'longitude' => $p['longitude'] ?? null,
                    'is_chain' => false,
                ]
            );

            $saved[] = $record;
        }

        return [
            'status' => 'harvested',
            'saved_count' => count($saved),
            'chains_filtered' => $chainsFiltered,
            'records' => $saved,
        ];
    }

    /**
     * Ingests 2-column price table.
     * 1. Yields exactly one Fact per row with page set and ZERO embedding writes for numeric column (TEST ANCHOR).
     * 2. Re-uploading with 1 changed price invalidates 1 Fact and creates 1 in the same commit (TEST ANCHOR).
     */
    public function ingestPriceTable(int $businessId, array $twoColumnRows, int $pageNumber = 1, string $commitId = 'commit_01'): array
    {
        return DB::transaction(function () use ($businessId, $twoColumnRows, $pageNumber, $commitId) {
            $factsCreated = 0;
            $factsInvalidated = 0;

            foreach ($twoColumnRows as $row) {
                $serviceName = $row['service'];
                $price = $row['price'];
                $factKey = "price:{$serviceName}";

                // Check existing active fact
                $existingFact = Fact::where('business_id', $businessId)
                    ->where('key', $factKey)
                    ->where('is_valid', true)
                    ->first();

                $factValue = json_encode([
                    'service' => $serviceName,
                    'price' => $price,
                    'page' => $pageNumber,
                    'numeric_embedding_vector' => null, // ZERO embedding writes for numeric column (TEST ANCHOR)
                ]);

                if ($existingFact) {
                    $existingData = json_decode($existingFact->value, true);
                    if ($existingData['price'] !== $price) {
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
            }

            return [
                'status' => 'price_table_ingested',
                'facts_created' => $factsCreated,
                'facts_invalidated' => $factsInvalidated,
                'page' => $pageNumber,
            ];
        });
    }
}
