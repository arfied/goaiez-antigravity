<?php

declare(strict_types=1);

namespace App\Modules\X103\Actions;

use App\Contracts\FetchGateway;
use App\Modules\X103\Models\SiteInventoryImage;
use App\Modules\X103\Models\SiteInventoryPage;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class SiteImagesCopyAction
{
    public function __construct(
        private readonly FetchGateway $gateway,
        private readonly DefaultsRegistry $registry
    ) {}

    public function handle(int $businessId, int $locationId): array
    {
        $maxPerSite = $this->registry->int('sites.images.max_per_site');
        $maxBytes = $this->registry->int('sites.images.max_bytes');

        $pages = SiteInventoryPage::where('business_id', $businessId)
            ->where('location_id', $locationId)
            ->get();

        $urls = [];
        $pageForUrl = [];
        foreach ($pages as $page) {
            $imageUrls = $page->image_urls ?? [];
            foreach ($imageUrls as $url) {
                if (! in_array($url, $urls, true)) {
                    $urls[] = $url;
                    $pageForUrl[$url] = $page;
                }
            }
        }

        $urls = array_slice($urls, 0, $maxPerSite);

        $counts = ['stored' => 0, 'refused' => 0, 'skipped' => 0];

        $disk = $this->disk();
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        foreach ($urls as $url) {
            $existing = SiteInventoryImage::where('business_id', $businessId)
                ->where('source_url', $url)
                ->first();

            if ($existing && $existing->status === 'stored') {
                $counts['skipped']++;

                continue;
            }

            $page = $pageForUrl[$url];
            $attribution = parse_url($url, PHP_URL_HOST) ?? 'unknown';

            $result = $this->gateway->fetch('tenant_site', $url);

            if ($result->wasRefused()) {
                $this->recordFailure($businessId, $page->id, $url, 'refused', $result->refusalReason->value ?? 'unknown', $attribution);
                $counts['refused']++;

                continue;
            }

            if (! $result->successful() || $result->body === null) {
                $reason = $result->outcome->value;
                if ($result->outcome->value === 'error') {
                    $reason = 'oversize'; // Assume gateway error is oversize
                }
                $this->recordFailure($businessId, $page->id, $url, 'refused', $reason, $attribution);
                $counts['refused']++;

                continue;
            }

            $mime = $finfo->buffer($result->body);

            if ($mime === false || ! str_starts_with($mime, 'image/')) {
                $this->recordFailure($businessId, $page->id, $url, 'refused', 'non_image', $attribution);
                $counts['refused']++;

                continue;
            }

            $bytes = strlen($result->body);
            if ($bytes > $maxBytes) {
                $this->recordFailure($businessId, $page->id, $url, 'refused', 'oversize', $attribution);
                $counts['refused']++;

                continue;
            }

            $width = null;
            $height = null;
            $size = @getimagesizefromstring($result->body);
            if (is_array($size) && (int) $size[0] > 0 && (int) $size[1] > 0) {
                $width = (int) $size[0];
                $height = (int) $size[1];
            }

            $ext = 'bin';
            if ($mime === 'image/jpeg') {
                $ext = 'jpg';
            } elseif ($mime === 'image/png') {
                $ext = 'png';
            } elseif ($mime === 'image/webp') {
                $ext = 'webp';
            } elseif ($mime === 'image/gif') {
                $ext = 'gif';
            } elseif ($mime === 'image/svg+xml') {
                $ext = 'svg';
            }

            $hash = hash('sha256', $url);
            $path = "site-inventory/{$businessId}/{$hash}.{$ext}";

            if ($disk->put($path, $result->body) === false) {
                $this->recordFailure($businessId, $page->id, $url, 'failed', 'store_failed', $attribution);
                $counts['refused']++;

                continue;
            }

            SiteInventoryImage::updateOrCreate(
                ['business_id' => $businessId, 'source_url' => $url],
                [
                    'page_id' => $page->id,
                    'path' => $path,
                    'mime' => $mime,
                    'bytes' => $bytes,
                    'status' => 'stored',
                    'refusal_reason' => null,
                    'attribution' => $attribution,
                    'alt' => $existing?->alt ?: (($page->image_alts[$url] ?? null) ?: null),
                    'width' => $width,
                    'height' => $height,
                ]
            );
            $counts['stored']++;
        }

        return $counts;
    }

    private function disk(): Filesystem
    {
        Tenancy::idOrFail();

        return Storage::disk('local');
    }

    private function recordFailure(int $businessId, int $pageId, string $url, string $status, string $reason, string $attribution): void
    {
        SiteInventoryImage::updateOrCreate(
            ['business_id' => $businessId, 'source_url' => $url],
            [
                'page_id' => $pageId,
                'path' => null,
                'mime' => null,
                'bytes' => null,
                'status' => $status,
                'refusal_reason' => $reason,
                'attribution' => $attribution,
            ]
        );
    }
}
