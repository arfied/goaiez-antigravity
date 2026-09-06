<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

use App\Models\Business;
use App\Modules\X176\Events\SchemaPublished;
use App\Modules\X176\Models\SchemaSnapshot;
use Illuminate\Support\Facades\Event;

final class SchemaRenderAction
{
    /**
     * Render schema.org JSON-LD and persist snapshot (TEST ANCHOR, G8-14, G8-32, G12-03).
     */
    public function handle(
        int $businessId,
        int $pageId,
        string $businessName,
        string $commitId,
        string $domainName,
        ?string $entityType = null,
        ?array $productOffers = null,
        ?array $videos = null,
        ?array $events = null
    ): array {
        if ($entityType === null) {
            $vertical = strtolower(trim((string) (Business::find($businessId)->vertical ?? '')));
            /** (R245) */
            $map = [
                'hvac' => 'HVACBusiness',
                'dental' => 'Dentist',
                'salon' => 'BeautySalon',
                'legal' => 'LegalService',
                'auto' => 'AutoRepair',
                'medical' => 'MedicalClinic',
                'plumbing' => 'Plumber',
            ];
            $entityType = $map[$vertical] ?? 'LocalBusiness';
        }

        $canonical = app(SeoRenderAction::class)
            ->handle($businessId, $pageId, $businessName, $commitId, $domainName)['canonical'];

        // Build valid schema.org structure (G8-32)
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => $entityType,
            'name' => $businessName,
            'url' => $canonical,
        ];

        if (! empty($productOffers)) {
            // Product schema from pricebook updated in same commit as Fact (TEST ANCHOR, G8-14)
            $jsonLd['hasOfferCatalog'] = [
                '@type' => 'OfferCatalog',
                'name' => 'Services Pricebook',
                'itemListElement' => array_map(fn ($p) => [
                    '@type' => 'Offer',
                    'itemOffered' => [
                        '@type' => 'Service',
                        'name' => $p['name'],
                    ],
                    'price' => $p['price'] ?? null,
                    'priceCurrency' => 'USD',
                ], $productOffers),
            ];
        }

        if (! empty($videos)) {
            // VideoObject injected on publish (TEST ANCHOR, G16-25)
            $jsonLd['video'] = array_map(fn ($v) => [
                '@type' => 'VideoObject',
                'name' => $v['name'] ?? null,
                'contentUrl' => $v['contentUrl'] ?? null,
                'uploadDate' => $v['uploadDate'] ?? null,
            ], $videos);
        }

        if (! empty($events)) {
            // Event injected on publish (TEST ANCHOR, G8-15)
            $jsonLd['event'] = array_map(fn ($e) => [
                '@type' => 'Event',
                'name' => $e['name'] ?? null,
                'startDate' => $e['startDate'] ?? null,
                'endDate' => $e['endDate'] ?? null,
            ], $events);
        }

        $isValid = $this->validateSchema($jsonLd);
        if (! $isValid) {
            return [
                'status' => 'refused',
                'refusal_code' => 'SCHEMA_INVALID',
            ];
        }

        $snapshot = SchemaSnapshot::updateOrCreate(
            ['business_id' => $businessId, 'page_id' => $pageId],
            [
                'entity_type' => $entityType,
                'json_ld' => $jsonLd,
                'commit_id' => $commitId, // Shared commit ID with Fact (TEST ANCHOR)
                'is_valid_schema' => $isValid,
            ]
        );

        Event::dispatch(new SchemaPublished(
            businessId: $businessId,
            pageId: $pageId,
            commitId: $commitId,
            entityType: $entityType
        ));

        return [
            'status' => 'published',
            'snapshot_id' => $snapshot->id,
            'commit_id' => $commitId,
            'json_ld' => $jsonLd,
        ];
    }

    private function validateSchema(array $schema): bool
    {
        if (($schema['@context'] ?? '') !== 'https://schema.org') {
            return false;
        }
        if (empty($schema['@type']) || empty($schema['name']) || empty($schema['url'])) {
            return false;
        }
        if (! is_string($schema['@type']) || ! is_string($schema['name']) || ! is_string($schema['url'])) {
            return false;
        }
        if (isset($schema['hasOfferCatalog'])) {
            $catalog = $schema['hasOfferCatalog'];
            if (($catalog['@type'] ?? '') !== 'OfferCatalog') {
                return false;
            }
            if (! isset($catalog['itemListElement']) || ! is_array($catalog['itemListElement'])) {
                return false;
            }
            foreach ($catalog['itemListElement'] as $item) {
                if (($item['@type'] ?? '') !== 'Offer' || ($item['itemOffered']['@type'] ?? '') !== 'Service') {
                    return false;
                }
                if (! array_key_exists('price', $item) || $item['price'] === null || ! isset($item['priceCurrency'])) {
                    return false;
                }
            }
        }

        if (isset($schema['video'])) {
            if (! is_array($schema['video'])) {
                return false;
            }
            foreach ($schema['video'] as $item) {
                if (($item['@type'] ?? '') !== 'VideoObject') {
                    return false;
                }
                foreach (['name', 'contentUrl', 'uploadDate'] as $k) {
                    if (! isset($item[$k]) || ! is_string($item[$k]) || $item[$k] === '') {
                        return false;
                    }
                }
            }
        }

        if (isset($schema['event'])) {
            if (! is_array($schema['event'])) {
                return false;
            }
            foreach ($schema['event'] as $item) {
                if (($item['@type'] ?? '') !== 'Event') {
                    return false;
                }
                foreach (['name', 'startDate', 'endDate'] as $k) {
                    if (! isset($item[$k]) || ! is_string($item[$k]) || $item[$k] === '') {
                        return false;
                    }
                }
            }
        }

        return true;
    }
}
