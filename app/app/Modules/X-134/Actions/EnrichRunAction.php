<?php

declare(strict_types=1);

namespace App\Modules\X134\Actions;

use App\Modules\X134\Events\EnrichmentRequested;
use App\Modules\X134\Models\EnrichmentField;
use App\Modules\X134\Models\EnrichmentRun;
use Illuminate\Support\Facades\Event;

final class EnrichRunAction
{
    private const USABILITY_CONFIDENCE_THRESHOLD = 0.70;

    /**
     * Executes firmographic, tech-stack (Wappalyzer) and pixel enrichment (G3-19, G3-57, G13-26).
     * A field with confidence < threshold is marked unusable and never appears in rendered templates (TEST ANCHOR).
     */
    public function enrich(
        int $businessId,
        string $domain,
        string $entityId,
        array $scrapedFields = []
    ): EnrichmentRun {
        $run = EnrichmentRun::create([
            'business_id' => $businessId,
            'domain' => $domain,
            'status' => 'completed',
            'fetched_at' => now(), // P-143: staleness is fetched_at, never an eviction (G3-08)
        ]);

        foreach ($scrapedFields as $field) {
            $key = $field['key'] ?? 'unknown';
            $val = $field['value'] ?? '';
            $source = $field['source'] ?? 'wappalyzer';
            $confidence = (float) ($field['confidence'] ?? 0.85);

            // TEST ANCHOR: confidence < threshold never in rendered template
            $isUsable = ($confidence >= self::USABILITY_CONFIDENCE_THRESHOLD);

            EnrichmentField::create([
                'business_id' => $businessId,
                'run_id' => $run->id,
                'entity_id' => $entityId,
                'field_key' => $key,
                'field_value' => (string) $val,
                'source' => $source,
                'confidence_score' => $confidence,
                'is_usable' => $isUsable,
            ]);
        }

        Event::dispatch(new EnrichmentRequested($businessId, $domain, $run->id));

        return $run;
    }
}
