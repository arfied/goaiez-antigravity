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
        ?array $events = null,
        ?array $address = null,
        ?array $breadcrumbs = null,
        ?array $faqs = null,
        string $pathPrefix = ''
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
            ->handle($businessId, $pageId, $businessName, $commitId, $domainName, $pathPrefix)['canonical'];

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
                'itemListElement' => array_map(fn ($p) => isset($p['price_max'])
                    ? [
                        '@type' => 'AggregateOffer',
                        'itemOffered' => [
                            '@type' => 'Service',
                            'name' => $p['name'],
                        ],
                        'lowPrice' => $p['price'] ?? null,
                        'highPrice' => $p['price_max'],
                        'priceCurrency' => 'USD',
                    ]
                    : [
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

        if (! empty($address)) {
            // LocalBusiness address schema (TEST ANCHOR, G8-22)
            $isAddressValid = true;
            foreach (['line1', 'city', 'region', 'postal_code', 'country'] as $key) {
                if (! isset($address[$key]) || ! is_string($address[$key]) || $address[$key] === '') {
                    $isAddressValid = false;
                    break;
                }
            }
            if ($isAddressValid) {
                $jsonLd['address'] = [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $address['line1'],
                    'addressLocality' => $address['city'],
                    'addressRegion' => $address['region'],
                    'postalCode' => $address['postal_code'],
                    'addressCountry' => $address['country'],
                ];
            }

            if (isset($address['lat'], $address['lng']) && is_numeric($address['lat']) && is_numeric($address['lng'])) {
                $jsonLd['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $address['lat'],
                    'longitude' => $address['lng'],
                ];
            }
        }

        if (! empty($breadcrumbs)) {
            // BreadcrumbGeneration (TEST ANCHOR, G8-04)
            $position = 1;
            $itemListElement = [];
            foreach ($breadcrumbs as $crumb) {
                $itemListElement[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => $crumb['name'],
                    'item' => 'https://'.$domainName.$pathPrefix.'/'.$crumb['slug'],
                ];
                $position++;
            }
            $jsonLd['breadcrumb'] = [
                '@type' => 'BreadcrumbList',
                'itemListElement' => $itemListElement,
            ];
        }

        if (! empty($faqs)) {
            // FAQPage schema from faq block (TEST ANCHOR, G8-16)
            $validFaqs = [];
            foreach ($faqs as $f) {
                if (isset($f['question'], $f['answer']) && is_string($f['question']) && is_string($f['answer']) && $f['question'] !== '' && $f['answer'] !== '') {
                    $validFaqs[] = [
                        '@type' => 'Question',
                        'name' => $f['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $f['answer'],
                        ],
                    ];
                }
            }
            if (! empty($validFaqs)) {
                $currentType = $jsonLd['@type'];
                $jsonLd['@type'] = (array) $currentType;
                $jsonLd['@type'][] = 'FAQPage';
                $jsonLd['mainEntity'] = $validFaqs;
            }
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
        // @type keeps empty() because isset on an empty array is true and $validType computes 0 === 0 for it
        if (empty($schema['@type']) || ! isset($schema['name']) || ! isset($schema['url'])) {
            return false;
        }
        $validType = is_string($schema['@type']) || (is_array($schema['@type']) && count(array_filter($schema['@type'], 'is_string')) === count($schema['@type']));
        if (! $validType || ! is_string($schema['name']) || trim($schema['name']) === '' || ! is_string($schema['url']) || trim($schema['url']) === '') {
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
                $type = $item['@type'] ?? '';
                if (! in_array($type, ['Offer', 'AggregateOffer'], true) || ($item['itemOffered']['@type'] ?? '') !== 'Service') {
                    return false;
                }
                if (! isset($item['priceCurrency'])) {
                    return false;
                }
                if ($type === 'Offer' && (! array_key_exists('price', $item) || $item['price'] === null)) {
                    return false;
                }
                if ($type === 'AggregateOffer' && (! isset($item['lowPrice'], $item['highPrice']) || $item['lowPrice'] > $item['highPrice'])) {
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

        if (isset($schema['address'])) {
            $addr = $schema['address'];
            if (! is_array($addr)) {
                return false;
            }
            if (($addr['@type'] ?? '') !== 'PostalAddress') {
                return false;
            }
            foreach (['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'] as $k) {
                if (! isset($addr[$k]) || ! is_string($addr[$k]) || $addr[$k] === '') {
                    return false;
                }
            }
        }

        if (isset($schema['geo'])) {
            $geo = $schema['geo'];
            if (! is_array($geo)) {
                return false;
            }
            if (($geo['@type'] ?? '') !== 'GeoCoordinates') {
                return false;
            }
            foreach (['latitude', 'longitude'] as $k) {
                if (! isset($geo[$k]) || ! is_numeric($geo[$k])) {
                    return false;
                }
            }
        }

        if (isset($schema['breadcrumb'])) {
            $bc = $schema['breadcrumb'];
            if (! is_array($bc) || ($bc['@type'] ?? '') !== 'BreadcrumbList') {
                return false;
            }
            if (! isset($bc['itemListElement']) || ! is_array($bc['itemListElement'])) {
                return false;
            }
            foreach ($bc['itemListElement'] as $item) {
                if (($item['@type'] ?? '') !== 'ListItem') {
                    return false;
                }
                if (! isset($item['position']) || ! is_numeric($item['position'])) {
                    return false;
                }
                if (! isset($item['name']) || ! is_string($item['name']) || $item['name'] === '') {
                    return false;
                }
                if (! isset($item['item']) || ! is_string($item['item']) || $item['item'] === '') {
                    return false;
                }
            }
        }

        if (isset($schema['mainEntity'])) {
            $me = $schema['mainEntity'];
            if (! is_array($me)) {
                return false;
            }
            foreach ($me as $item) {
                if (($item['@type'] ?? '') !== 'Question') {
                    return false;
                }
                if (! isset($item['name']) || ! is_string($item['name']) || $item['name'] === '') {
                    return false;
                }
                if (! isset($item['acceptedAnswer']) || ! is_array($item['acceptedAnswer']) || ($item['acceptedAnswer']['@type'] ?? '') !== 'Answer') {
                    return false;
                }
                if (! isset($item['acceptedAnswer']['text']) || ! is_string($item['acceptedAnswer']['text']) || $item['acceptedAnswer']['text'] === '') {
                    return false;
                }
            }
        }

        return true;
    }
}
