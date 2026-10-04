<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Contracts\FetchGateway;
use App\Enums\FetchOutcome;
use App\Enums\FetchRefusalReason;
use App\Models\Location;
use App\Modules\X103\Domain\SiteBrandSignals;
use App\Modules\X103\Domain\SiteProductSignals;
use App\Modules\X103\Jobs\SiteCrawlContinueJob;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Config\DefaultsRegistry;
use DOMDocument;
use Illuminate\Database\Eloquent\Builder;

final class SiteCrawlAction
{
    /** Stylesheets read for the brand, from the home page only and only from the site's own host. */
    private const MAX_STYLESHEETS = 1;

    /** A stylesheet whose address looks like the site's own theme is read before any plugin's. */
    private const THEME_STYLESHEET = '#/themes?/|style|main|site|app#i';

    /**
     * The shared website-reading budget (tenant_site) is four reads a minute for the whole platform, and a crawl that
     * marked every page past it as refused read about four pages of any site, every time (the owner's build, 2026-10-03:
     * "pages 3, refused 13"). A page the budget turns away is QUEUED instead, and SiteCrawlContinueJob reads the queue a
     * minute later, round after round, until it is empty — at most this many rounds.
     */
    public const MAX_ROUNDS = 20;

    public const RESUME_AFTER_SECONDS = 65;

    /**
     * A page the crawl's page limit (sites.crawl.max_pages) leaves unread. It is no longer "cut short": without this, a site
     * larger than the limit would count as unfinished for ever and every build would crawl it again.
     */
    public const PAST_LIMIT = 'max_pages';

    public function __construct(
        private readonly FetchGateway $fetcher,
        private readonly DefaultsRegistry $registry
    ) {}

    /**
     * @return array{status: string, pages?: int, refused?: int, queued?: int, reason?: string}
     */
    public function handle(int $businessId, int $locationId): array
    {
        $location = Location::find($locationId);
        if (! $location || ! $location->website_url || ! $location->website_confirmed_at) {
            return ['status' => 'refused', 'reason' => 'no_website'];
        }
        $startUrl = $location->website_url;

        // A changed website: the pages (and, by cascade, the image rows) crawled from this location's PREVIOUS website are
        // forgotten before the new one is read, so the old site's words, brand and photos stop feeding drafts and designs (the
        // owner testing another business's site, 2026-10-03). The picture files stay on disk, so a page already using one keeps
        // it. Only when the address names a host: an unreadable one must never forget the whole crawl.
        if (self::hostOf($startUrl) !== '') {
            SiteInventoryPage::where('business_id', $businessId)->where('location_id', $locationId)
                ->whereNot(fn (Builder $q) => self::onWebsite($q, $startUrl))
                ->delete();
        }

        // Pages an earlier crawl could not read because the budget ran out are read again now, linked or not — otherwise
        // a page the site no longer links to would stay "cut short" for ever and every build would re-crawl.
        $pending = array_map('strval', self::cutShort($businessId, $locationId)->orderBy('id')->pluck('url')->all());
        $queue = array_values(array_unique([$startUrl, ...$pending]));

        return $this->crawl($businessId, $locationId, $startUrl, $queue, array_fill_keys($queue, true), $this->registry->int('sites.crawl.max_pages'), 1);
    }

    /**
     * Narrows a query on SiteInventoryPage to the pages of the website $websiteUrl names: its host, with or without "www.",
     * over http or https. A website with no readable host matches nothing.
     *
     * @param  Builder<SiteInventoryPage>  $query
     * @return Builder<SiteInventoryPage>
     */
    public static function onWebsite(Builder $query, string $websiteUrl): Builder
    {
        $host = self::hostOf($websiteUrl);
        if ($host === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $w) use ($host): void {
            foreach (['http://', 'https://'] as $scheme) {
                foreach (['', 'www.'] as $sub) {
                    $w->orWhere('url', $scheme.$sub.$host)->orWhere('url', 'like', $scheme.$sub.$host.'/%');
                }
            }
        });
    }

    private static function hostOf(string $url): string
    {
        return (string) preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    /**
     * The pages a crawl did not finish because the shared budget ran out: queued for a later round, or — before
     * 2026-10-03 — recorded as refused for the budget. While any exist the inventory is not complete.
     *
     * @return Builder<SiteInventoryPage>
     */
    public static function cutShort(int $businessId, int $locationId): Builder
    {
        return SiteInventoryPage::where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->where(fn ($q) => $q->where('status', 'queued')
                ->orWhere(fn ($q) => $q->where('status', 'refused')->where('refusal_reason', FetchRefusalReason::RateBudget->value)));
    }

    /**
     * Reads the pages an earlier round could not (cutShort()), and every new page they link to, within the $remaining pages
     * the crawl that queued them had left. The allowance travels with the job: counting it from every row the location has
     * ever had — rows from crawls days ago included — starved the very first resumed round on production (2026-10-03: one
     * read, then nothing).
     *
     * @return array{status: string, pages?: int, refused?: int, queued?: int, reason?: string}
     */
    public function continue(int $businessId, int $locationId, int $round, int $remaining): array
    {
        $location = Location::find($locationId);
        if (! $location || ! $location->website_url || ! $location->website_confirmed_at) {
            return ['status' => 'refused', 'reason' => 'no_website'];
        }

        $queue = array_values(array_map('strval', self::cutShort($businessId, $locationId)->orderBy('id')->pluck('url')->all()));
        if ($queue === []) {
            return ['status' => 'fetched', 'pages' => 0, 'refused' => 0, 'queued' => 0];
        }
        $seen = array_fill_keys(array_map('strval', SiteInventoryPage::where('business_id', $businessId)->where('location_id', $locationId)->pluck('url')->all()), true);

        return $this->crawl($businessId, $locationId, $location->website_url, $queue, $seen, max(0, $remaining), $round);
    }

    /**
     * @param  list<string>  $queue
     * @param  array<string, bool>  $seen
     * @return array{status: string, pages: int, refused: int, queued: int}
     */
    private function crawl(int $businessId, int $locationId, string $startUrl, array $queue, array $seen, int $maxPages, int $round): array
    {
        $host = parse_url($startUrl, PHP_URL_HOST);
        $pagesCount = 0;
        $refusedCount = 0;
        $paused = false;

        while (! empty($queue) && ($pagesCount + $refusedCount) < $maxPages) {
            $url = array_shift($queue);

            $result = $this->fetcher->fetch('tenant_site', $url);

            // The budget turning a page away says nothing about the page: keep it, and the rest, for the next round.
            if ($result->refusalReason === FetchRefusalReason::RateBudget) {
                array_unshift($queue, $url);
                $paused = true;
                break;
            }

            if (! $result->successful()) {
                SiteInventoryPage::updateOrCreate(
                    ['business_id' => $businessId, 'url' => $url],
                    [
                        'location_id' => $locationId,
                        'status' => $result->wasRefused() ? 'refused' : 'failed',
                        'refusal_reason' => match (true) {
                            $result->refusalReason !== null => $result->refusalReason->value,
                            $result->outcome === FetchOutcome::Blocked, $result->outcome === FetchOutcome::Challenge => 'blocked_by_site',
                            default => 'unknown',
                        },
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
            // The page's own words: <main> if it has one, and never its menu, header, footer, form or code.
            // textContent includes <script>/<style> contents, which is how menus and JS reached site copy.
            $textRoot = $dom->getElementsByTagName('main')->item(0) ?? $bodyNode;
            $text = null;
            $clone = $textRoot?->cloneNode(true);
            if ($clone instanceof \DOMElement) {
                foreach (['script', 'style', 'noscript', 'template', 'svg', 'nav', 'header', 'footer', 'form'] as $tag) {
                    $found = [];
                    foreach ($clone->getElementsByTagName($tag) as $node) {
                        $found[] = $node;
                    }
                    foreach ($found as $node) {
                        $node->parentNode?->removeChild($node);
                    }
                }
                $text = preg_replace('/\s+/', ' ', trim($clone->textContent));
            }

            $imageUrls = [];
            $imageAlts = [];
            foreach ($dom->getElementsByTagName('img') as $node) {
                $src = $node->getAttribute('src');
                if ($src) {
                    $resolved = $this->resolveUrl($url, $src);
                    $imageUrls[] = $resolved;
                    $alt = trim(preg_replace('/\s+/', ' ', $node->getAttribute('alt')) ?? '');
                    if ($resolved && $alt !== '' && ! isset($imageAlts[$resolved])) {
                        $imageAlts[$resolved] = mb_substr($alt, 0, 160);
                    }
                }
            }
            $imageUrls = array_values(array_unique(array_filter($imageUrls)));
            // The store's own products (SiteProductSignals). Their pictures go first in the page's images, so the image copy
            // (SiteImagesCopyAction, capped per site) stores them before decorative ones.
            $products = SiteProductSignals::read((string) $result->body, $url);
            $imageUrls = array_values(array_unique(array_merge(array_filter(array_column($products, 'image_url')), $imageUrls)));

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
                } elseif (str_contains($href, '/cdn-cgi/l/email-protection')) {
                    // Cloudflare rewrites every mailto: into this, with the address XOR-encoded in the fragment. It is an
                    // address, never a page: following them used 17 of one site's 25-page allowance (production, 2026-10-03).
                    $cfEmail = self::cloudflareEmail((string) parse_url($href, PHP_URL_FRAGMENT));
                    if ($cfEmail !== null) {
                        $emails[] = $cfEmail;
                    }
                } else {
                    $resolved = $this->resolveUrl($url, $href);
                    if ($resolved) {
                        // A #fragment is the same page; /cdn-cgi/ is Cloudflare's own machinery, never the site's.
                        $resolved = explode('#', $resolved, 2)[0];
                        $parsedHost = parse_url($resolved, PHP_URL_HOST);
                        if ($parsedHost === $host && ! str_starts_with((string) parse_url($resolved, PHP_URL_PATH), '/cdn-cgi/')) {
                            $linksOut[] = $resolved;
                            if (! isset($seen[$resolved])) {
                                $seen[$resolved] = true;
                                $queue[] = $resolved;
                            }
                        }
                    }
                }
            }

            // Cloudflare's other form of the same obfuscation: <a class="__cf_email__" data-cfemail="…">.
            foreach ((new \DOMXPath($dom))->query('//*[@data-cfemail]') ?: [] as $node) {
                if ($node instanceof \DOMElement) {
                    $cfEmail = self::cloudflareEmail($node->getAttribute('data-cfemail'));
                    if ($cfEmail !== null) {
                        $emails[] = $cfEmail;
                    }
                }
            }

            $phones = array_values(array_unique($phones));
            $emails = array_values(array_unique($emails));
            $linksOut = array_values(array_unique($linksOut));

            // The brand (colours and fonts) comes from the home page and ONE of the site's own stylesheets — the one most likely
            // to be the theme's. One, not more: tenant_site's budget is four fetches a minute, and pages come first.
            $brand = [];
            if ($url === $startUrl) {
                $candidates = [];
                foreach ($dom->getElementsByTagName('link') as $node) {
                    if (! str_contains(strtolower($node->getAttribute('rel')), 'stylesheet')) {
                        continue;
                    }
                    $cssUrl = $this->resolveUrl($url, $node->getAttribute('href'));
                    if ($cssUrl !== null && parse_url($cssUrl, PHP_URL_HOST) === $host) {
                        $candidates[] = $cssUrl;
                    }
                }
                $isTheme = fn (string $cssUrl): int => preg_match(self::THEME_STYLESHEET, (string) parse_url($cssUrl, PHP_URL_PATH)) ? 0 : 1;
                usort($candidates, fn (string $a, string $b) => $isTheme($a) <=> $isTheme($b));
                $stylesheets = [];
                foreach (array_slice($candidates, 0, self::MAX_STYLESHEETS) as $cssUrl) {
                    $cssResult = $this->fetcher->fetch('tenant_site', $cssUrl);
                    if ($cssResult->successful()) {
                        $stylesheets[] = mb_substr((string) $cssResult->body, 0, 200_000);
                    }
                }
                $brand = SiteBrandSignals::read((string) $result->body, $stylesheets);
            }

            SiteInventoryPage::updateOrCreate(
                ['business_id' => $businessId, 'url' => $url],
                [
                    'brand' => $brand === [] ? null : $brand,
                    'products' => $products === [] ? null : $products,
                    'location_id' => $locationId,
                    'title' => $title ? trim($title) : null,
                    'headings' => $headings,
                    'text' => $text,
                    'image_urls' => $imageUrls,
                    'image_alts' => $imageAlts,
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

        $remaining = max(0, $maxPages - $pagesCount - $refusedCount);
        $queuedCount = 0;
        if ($paused) {
            foreach (array_slice($queue, 0, $remaining) as $queuedUrl) {
                SiteInventoryPage::updateOrCreate(
                    ['business_id' => $businessId, 'url' => $queuedUrl],
                    ['location_id' => $locationId, 'status' => 'queued', 'refusal_reason' => null]
                );
                $queuedCount++;
            }
            if ($queuedCount > 0 && $round < self::MAX_ROUNDS) {
                SiteCrawlContinueJob::dispatch($businessId, $locationId, $round + 1, $remaining)->delay(now()->addSeconds(self::RESUME_AFTER_SECONDS));
            }
        }

        // What the page limit leaves unread is past the limit, not cut short (PAST_LIMIT).
        $pastLimit = array_slice($queue, $paused ? $remaining : 0);
        if ($pastLimit !== []) {
            self::cutShort($businessId, $locationId)->whereIn('url', $pastLimit)->update(['status' => 'refused', 'refusal_reason' => self::PAST_LIMIT]);
        }

        return [
            'status' => 'fetched',
            'pages' => $pagesCount,
            'refused' => $refusedCount,
            'queued' => $queuedCount,
        ];
    }

    /**
     * Decodes Cloudflare's email obfuscation: hex bytes, the first of which is the XOR key for the rest. Anything that does
     * not decode to a valid address is ignored.
     */
    private static function cloudflareEmail(string $hex): ?string
    {
        if (strlen($hex) < 4 || strlen($hex) % 2 !== 0 || ! ctype_xdigit($hex)) {
            return null;
        }
        $key = (int) hexdec(substr($hex, 0, 2));
        $email = '';
        for ($i = 2; $i < strlen($hex); $i += 2) {
            $email .= chr((int) hexdec(substr($hex, $i, 2)) ^ $key);
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
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
