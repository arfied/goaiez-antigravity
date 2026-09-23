<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Contracts\FetchGateway;
use App\Models\Location;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Config\DefaultsRegistry;
use DOMDocument;

final class SiteCrawlAction
{
    public function __construct(
        private readonly FetchGateway $fetcher,
        private readonly DefaultsRegistry $registry
    ) {}

    /**
     * @return array{status: string, pages?: int, refused?: int, reason?: string}
     */
    public function handle(int $businessId, int $locationId): array
    {
        $location = Location::find($locationId);
        if (! $location || ! $location->website_url || ! $location->website_confirmed_at) {
            return ['status' => 'refused', 'reason' => 'no_website'];
        }

        $maxPages = $this->registry->int('sites.crawl.max_pages');
        $startUrl = $location->website_url;
        $host = parse_url($startUrl, PHP_URL_HOST);

        $queue = [$startUrl];
        $seen = [$startUrl => true];
        $pagesCount = 0;
        $refusedCount = 0;

        while (! empty($queue) && ($pagesCount + $refusedCount) < $maxPages) {
            $url = array_shift($queue);

            $result = $this->fetcher->fetch('tenant_site', $url);

            if (! $result->successful()) {
                SiteInventoryPage::updateOrCreate(
                    ['business_id' => $businessId, 'url' => $url],
                    [
                        'location_id' => $locationId,
                        'status' => $result->wasRefused() ? 'refused' : 'failed',
                        'refusal_reason' => $result->refusalReason ? $result->refusalReason->value : 'unknown',
                        'fetched_at' => now(),
                    ]
                );
                $refusedCount++;

                continue;
            }

            $dom = new DOMDocument;
            // Suppress warnings from malformed HTML
            $internalErrors = libxml_use_internal_errors(true);
            $dom->loadHTML($result->body);
            libxml_use_internal_errors($internalErrors);

            $title = $dom->getElementsByTagName('title')->item(0)?->textContent;

            $headings = [];
            for ($i = 1; $i <= 6; $i++) {
                foreach ($dom->getElementsByTagName('h'.$i) as $node) {
                    $text = trim($node->textContent);
                    if ($text !== '') {
                        $headings[] = $text;
                    }
                }
            }

            $bodyNode = $dom->getElementsByTagName('body')->item(0);
            $text = $bodyNode ? preg_replace('/\s+/', ' ', trim($bodyNode->textContent)) : null;

            $imageUrls = [];
            foreach ($dom->getElementsByTagName('img') as $node) {
                $src = $node->getAttribute('src');
                if ($src) {
                    $imageUrls[] = $this->resolveUrl($url, $src);
                }
            }
            $imageUrls = array_values(array_unique(array_filter($imageUrls)));

            $phones = [];
            $emails = [];
            $linksOut = [];

            foreach ($dom->getElementsByTagName('a') as $node) {
                $href = $node->getAttribute('href');
                if (! $href) {
                    continue;
                }

                if (str_starts_with(strtolower($href), 'tel:')) {
                    $phones[] = substr($href, 4);
                } elseif (str_starts_with(strtolower($href), 'mailto:')) {
                    $emails[] = substr($href, 7);
                } else {
                    $resolved = $this->resolveUrl($url, $href);
                    if ($resolved) {
                        $parsedHost = parse_url($resolved, PHP_URL_HOST);
                        if ($parsedHost === $host) {
                            $linksOut[] = $resolved;
                            if (! isset($seen[$resolved])) {
                                $seen[$resolved] = true;
                                $queue[] = $resolved;
                            }
                        }
                    }
                }
            }

            $phones = array_values(array_unique($phones));
            $emails = array_values(array_unique($emails));
            $linksOut = array_values(array_unique($linksOut));

            SiteInventoryPage::updateOrCreate(
                ['business_id' => $businessId, 'url' => $url],
                [
                    'location_id' => $locationId,
                    'title' => $title ? trim($title) : null,
                    'headings' => $headings,
                    'text' => $text,
                    'image_urls' => $imageUrls,
                    'phones' => $phones,
                    'emails' => $emails,
                    'links_out' => $linksOut,
                    'status' => 'fetched',
                    'refusal_reason' => null,
                    'fetched_at' => now(),
                ]
            );

            $pagesCount++;
        }

        return [
            'status' => 'fetched',
            'pages' => $pagesCount,
            'refused' => $refusedCount,
        ];
    }

    private function resolveUrl(string $base, string $rel): ?string
    {
        if (parse_url($rel, PHP_URL_SCHEME) != '') {
            return $rel;
        }

        if ($rel === '' || $rel[0] === '#') {
            return null; // Not a new page
        }

        $parsedBase = parse_url($base);
        $scheme = $parsedBase['scheme'] ?? 'http';
        $host = $parsedBase['host'] ?? '';

        if ($rel[0] === '/' && isset($rel[1]) && $rel[1] === '/') {
            return $scheme.':'.$rel;
        }
        if ($rel[0] === '/') {
            return $scheme.'://'.$host.$rel;
        }

        $path = $parsedBase['path'] ?? '/';
        if (substr($path, -1) !== '/') {
            $path = dirname($path);
            if ($path === '\\') {
                $path = '/';
            }
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
        }

        // Extremely simple resolution for this requirement
        return $scheme.'://'.$host.$path.$rel;
    }
}
