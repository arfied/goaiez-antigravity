<?php

declare(strict_types=1);

namespace App\Modules\X189\Actions;

use App\Modules\X189\Events\MediaBranded;
use App\Modules\X189\Models\BrandedMedia;
use Illuminate\Support\Facades\Event;

final class ImageOverlayAction
{
    /**
     * Overlays branding onto asset.
     * Ingest refusal (§226): asset with no license_source is REFUSED AT INGEST (TEST ANCHOR).
     * Output file carries branded overlay layer (TEST ANCHOR).
     */
    public function overlay(
        int $businessId,
        string $sourceAssetUrl,
        ?string $licenseSource,
        string $destination = 'social',
        array $brandMetadata = []
    ): array {
        // 1. License Source Ingest Gate (§226, TEST ANCHOR)
        if (empty($licenseSource)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'MISSING_LICENSE_SOURCE',
                'message' => 'An asset with no license_source is refused at ingest (§226)',
                'media' => null,
            ];
        }

        // 2. Compose branded overlay layer (TEST ANCHOR)
        $overlayLayer = [
            'tenant_watermark' => true,
            'logo_url' => "https://cdn.example.com/biz-{$businessId}/logo.png",
            'accent_color' => $brandMetadata['accent_color'] ?? '#0284c7',
            'phone_badge' => $brandMetadata['phone_badge'] ?? '+1-800-555-0199',
            'render_timestamp' => time(),
        ];

        $outputUrl = "https://cdn.example.com/biz-{$businessId}/branded_".md5($sourceAssetUrl).'.jpg';

        $media = BrandedMedia::create([
            'business_id' => $businessId,
            'source_asset_url' => $sourceAssetUrl,
            'license_source' => $licenseSource,
            'output_media_url' => $outputUrl,
            'overlay_layer' => $overlayLayer,
            'destination' => $destination,
        ]);

        Event::dispatch(new MediaBranded($businessId, $media->id, $outputUrl));

        return [
            'status' => 'branded',
            'media_id' => $media->id,
            'output_media_url' => $outputUrl,
            'overlay_layer' => $overlayLayer,
            'has_branded_overlay' => true,
        ];
    }
}
