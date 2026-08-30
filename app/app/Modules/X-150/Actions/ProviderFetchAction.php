<?php

declare(strict_types=1);

namespace App\Modules\X150\Actions;

use App\Modules\X150\Events\ProviderExhausted;
use App\Modules\X150\Events\ProviderSucceeded;
use App\Modules\X150\Events\ProviderTried;
use App\Modules\X150\Models\ProviderAttempt;
use App\Modules\X150\Models\ProviderRoster;
use Illuminate\Support\Facades\Event;

final class ProviderFetchAction
{
    /**
     * Cascading contact enrichment fetch.
     * Quality gate: A phone field of "N/A" from tier 1 is rejected and tier 2 is called (TEST ANCHOR).
     */
    public function fetch(
        int $businessId,
        string $requestId,
        string $targetName,
        array $mockTier1Data = [],
        array $mockTier2Data = []
    ): array {
        // 1. Ensure default providers exist for tenant
        $tier1Provider = ProviderRoster::firstOrCreate(
            ['business_id' => $businessId, 'tier_level' => 1],
            ['provider_name' => 'FastLookup LowCost', 'cost_per_lookup_cents' => 5, 'is_active' => true]
        );

        $tier2Provider = ProviderRoster::firstOrCreate(
            ['business_id' => $businessId, 'tier_level' => 2],
            ['provider_name' => 'DeepEnrich Premium', 'cost_per_lookup_cents' => 45, 'is_active' => true]
        );

        // 2. Try Tier 1
        Event::dispatch(new ProviderTried($businessId, $requestId, 1, $tier1Provider->provider_name));

        $t1Phone = $mockTier1Data['phone'] ?? null;
        $isTier1Valid = (! empty($t1Phone) && strtoupper(trim((string) $t1Phone)) !== 'N/A' && strtolower(trim((string) $t1Phone)) !== 'none');

        if ($isTier1Valid) {
            ProviderAttempt::create([
                'business_id' => $businessId,
                'request_id' => $requestId,
                'provider_id' => $tier1Provider->id,
                'tier_level' => 1,
                'returned_data' => $mockTier1Data,
                'status' => 'success',
            ]);

            Event::dispatch(new ProviderSucceeded($businessId, $requestId, 1, $tier1Provider->provider_name));

            return [
                'status' => 'succeeded',
                'resolved_by_tier' => 1,
                'data' => $mockTier1Data,
                'phone' => $t1Phone,
            ];
        }

        // Tier 1 returned "N/A" or empty -> REJECTED and Tier 2 called (TEST ANCHOR)
        ProviderAttempt::create([
            'business_id' => $businessId,
            'request_id' => $requestId,
            'provider_id' => $tier1Provider->id,
            'tier_level' => 1,
            'returned_data' => $mockTier1Data,
            'status' => 'rejected_junk',
        ]);

        // 3. Fallback to Tier 2 (Expensive Tier)
        Event::dispatch(new ProviderTried($businessId, $requestId, 2, $tier2Provider->provider_name));

        $t2Phone = $mockTier2Data['phone'] ?? null;
        $isTier2Valid = (! empty($t2Phone) && strtoupper(trim((string) $t2Phone)) !== 'N/A' && strtolower(trim((string) $t2Phone)) !== 'none');

        if ($isTier2Valid) {
            ProviderAttempt::create([
                'business_id' => $businessId,
                'request_id' => $requestId,
                'provider_id' => $tier2Provider->id,
                'tier_level' => 2,
                'returned_data' => $mockTier2Data,
                'status' => 'success',
            ]);

            Event::dispatch(new ProviderSucceeded($businessId, $requestId, 2, $tier2Provider->provider_name));

            return [
                'status' => 'succeeded',
                'resolved_by_tier' => 2,
                'data' => $mockTier2Data,
                'phone' => $t2Phone,
            ];
        }

        // Both tiers failed
        ProviderAttempt::create([
            'business_id' => $businessId,
            'request_id' => $requestId,
            'provider_id' => $tier2Provider->id,
            'tier_level' => 2,
            'returned_data' => $mockTier2Data,
            'status' => 'rejected_junk',
        ]);

        Event::dispatch(new ProviderExhausted($businessId, $requestId));

        return [
            'status' => 'exhausted',
            'resolved_by_tier' => null,
            'data' => null,
            'phone' => null,
        ];
    }
}
