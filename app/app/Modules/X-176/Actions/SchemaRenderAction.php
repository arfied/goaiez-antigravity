<?php

declare(strict_types=1);

namespace App\Modules\X176\Actions;

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
        ?string $entityType = 'LocalBusiness',
        ?array $productOffers = null
    ): array {
        // Build valid schema.org structure (G8-32)
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@type' => $entityType ?? 'LocalBusiness',
            'name' => $businessName,
            'url' => "https://example.com/pages/{$pageId}",
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
                    'price' => $p['price'],
                    'priceCurrency' => 'USD',
                ], $productOffers),
            ];
        }

        $snapshot = SchemaSnapshot::updateOrCreate(
            ['business_id' => $businessId, 'page_id' => $pageId],
            [
                'entity_type' => $entityType,
                'json_ld' => $jsonLd,
                'commit_id' => $commitId, // Shared commit ID with Fact (TEST ANCHOR)
                'is_valid_schema' => true,
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
}
