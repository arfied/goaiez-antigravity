<?php

declare(strict_types=1);

namespace App\Services\Images;

use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pixabay — free stock photos (the boss's template brief, 2026-10-04: "a vertical-specific approved stock set" when the business
 * has too few photos of its own). Read from https://pixabay.com/api/docs/ on 2026-10-04:
 *  - GET https://pixabay.com/api/?key=…&q=… — `q` at most 100 characters; 100 requests per 60 seconds per key; HTTP 429 past it.
 *  - "Requests must be cached for 24 hours" — every successful search is cached for a day.
 *  - "permanent hotlinking of images … is not allowed. If you intend to use the images, please download them to your server
 *    first" — download() fetches the picture once for the caller to store; no Pixabay URL is ever put on a page.
 *  - `largeImageURL` is at most 1280px on its long side and needs no extra approval (full size does).
 * The Content License (pixabay.com/service/license-summary): free, commercial use allowed, no attribution required; never in a
 * misleading way and never with recognisable brands — so callers use stock only for general scenes, never as the business's own
 * work, staff or premises.
 */
final class PixabayClient
{
    public const CREDENTIAL = 'pixabay_api_key';

    private const SEARCH = 'https://pixabay.com/api/';

    /** The only hosts a picture is downloaded from; a URL in a response that names any other is refused. */
    private const PICTURE_HOSTS = ['pixabay.com', 'cdn.pixabay.com'];

    /** A picture larger than this is refused rather than stored. */
    private const MAX_BYTES = 8_000_000;

    public function configured(): bool
    {
        return PlatformCredentials::has(self::CREDENTIAL);
    }

    /**
     * Horizontal photos for a query, safe search on; [] when the key is not set, the search is refused or Pixabay cannot be
     * reached. A failed search is not cached, so the next design tries again.
     *
     * @return list<array{id: int, tags: string, url: string, width: int, height: int, page_url: string}>
     */
    public function search(string $query, ?int $businessId = null): array
    {
        $query = mb_substr(trim($query), 0, 100);
        if ($query === '' || ! $this->configured()) {
            return [];
        }
        $cacheKey = 'pixabay:search:'.md5($query);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = VendorLog::timed('pixabay', 'GET', self::SEARCH, fn (): Response => Http::timeout(20)->get(self::SEARCH, [
                'key' => PlatformCredentials::get(self::CREDENTIAL),
                'q' => $query,
                'image_type' => 'photo',
                'orientation' => 'horizontal',
                'safesearch' => 'true',
                'per_page' => 30,
            ]), $businessId);
        } catch (ConnectionException) {
            VendorLog::failure('pixabay', 'GET', self::SEARCH, ConnectionException::class, $businessId);

            return [];
        }
        if (! $response->successful()) {
            VendorLog::failure('pixabay', 'GET', self::SEARCH, 'http_'.$response->status(), $businessId);

            return [];
        }

        $hits = [];
        foreach ((array) $response->json('hits', []) as $hit) {
            if (! is_array($hit) || ! is_string($hit['largeImageURL'] ?? null) || ! is_numeric($hit['id'] ?? null)) {
                continue;
            }
            $hits[] = [
                'id' => (int) $hit['id'],
                'tags' => is_string($hit['tags'] ?? null) ? $hit['tags'] : '',
                'url' => $hit['largeImageURL'],
                'width' => (int) ($hit['imageWidth'] ?? 0),
                'height' => (int) ($hit['imageHeight'] ?? 0),
                'page_url' => is_string($hit['pageURL'] ?? null) ? $hit['pageURL'] : '',
            ];
        }
        Cache::put($cacheKey, $hits, now()->addDay());

        return $hits;
    }

    /** The picture's bytes, or null when the URL is not a Pixabay picture, the download fails, or it is too large. */
    public function download(string $url, ?int $businessId = null): ?string
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? null) !== 'https' || ! in_array(strtolower((string) ($parts['host'] ?? '')), self::PICTURE_HOSTS, true)) {
            return null;
        }
        try {
            $response = VendorLog::timed('pixabay', 'GET', 'https://'.$parts['host'].'/', fn (): Response => Http::timeout(30)->get($url), $businessId);
        } catch (ConnectionException) {
            VendorLog::failure('pixabay', 'GET', 'https://'.$parts['host'].'/', ConnectionException::class, $businessId);

            return null;
        }
        $bytes = $response->successful() ? $response->body() : '';

        return $bytes !== '' && strlen($bytes) <= self::MAX_BYTES ? $bytes : null;
    }
}
