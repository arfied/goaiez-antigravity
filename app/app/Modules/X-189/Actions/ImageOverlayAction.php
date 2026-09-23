<?php

declare(strict_types=1);

namespace App\Modules\X189\Actions;

use App\Contracts\FetchGateway;
use App\Modules\X189\Events\MediaBranded;
use App\Modules\X189\Models\BrandCard;
use App\Modules\X189\Models\BrandedMedia;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

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

        if (str_starts_with($sourceAssetUrl, 'storage:')) {
            $path = substr($sourceAssetUrl, 8);
            if (! Storage::disk('local')->exists($path)) {
                return ['status' => 'refused', 'refusal_code' => 'SOURCE_NOT_FOUND'];
            }
            $bytes = Storage::disk('local')->get($path);
        } else {
            $fetch = app(FetchGateway::class)->fetch('tenant_site', $sourceAssetUrl);
            if (! $fetch->successful()) {
                return ['status' => 'refused', 'refusal_code' => 'FETCH_FAILED'];
            }
            $bytes = $fetch->body;
        }

        $canvas = @imagecreatefromstring($bytes);
        if (! $canvas) {
            return ['status' => 'refused', 'refusal_code' => 'UNREADABLE_IMAGE'];
        }

        $card = BrandCard::where('business_id', $businessId)->first();
        if (! $card) {
            $card = app(BrandCardEnsureAction::class)->handle($businessId);
        }

        $width = imagesx($canvas);
        $height = imagesy($canvas);

        $hex = ltrim($card->accent_color ?? '#0284c7', '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $accentColor = imagecolorallocate($canvas, $r, $g, $b);

        $barHeight = (int) ($height * 0.10);
        $barTop = $height - $barHeight;
        imagefilledrectangle($canvas, 0, $barTop, $width, $height, $accentColor);

        $textColor = imagecolorallocate($canvas, 255, 255, 255);
        if ($card->badge_text) {
            $fontPath = app(DefaultsRegistry::class)->string('campaigns.overlay_font_path');
            if ($fontPath && ! str_starts_with($fontPath, DIRECTORY_SEPARATOR)) {
                $fontPath = base_path($fontPath);
            }
            if ($fontPath && file_exists($fontPath)) {
                $fontSize = $barHeight * 0.4;
                $box = @imagettfbbox($fontSize, 0, $fontPath, $card->badge_text);
                if ($box) {
                    $textWidth = $box[2] - $box[0];
                    $x = (int) (($width - $textWidth) / 2);
                    $y = $barTop + (int) ($barHeight / 2) + (int) (($box[1] - $box[7]) / 2);
                    @imagettftext($canvas, $fontSize, 0, $x, $y, $textColor, $fontPath, $card->badge_text);
                }
            }
        }

        $logoPresent = false;
        if ($card->logo_path && Storage::disk('local')->exists($card->logo_path)) {
            $logoBytes = Storage::disk('local')->get($card->logo_path);
            $logo = @imagecreatefromstring($logoBytes);
            if ($logo) {
                $logoPresent = true;
                $logoW = imagesx($logo);
                $logoH = imagesy($logo);
                $targetLogoW = (int) ($width * 0.12);
                $targetLogoH = (int) ($logoH * ($targetLogoW / $logoW));
                imagecopyresampled($canvas, $logo, 0, 0, 0, 0, $targetLogoW, $targetLogoH, $logoW, $logoH);
                imagedestroy($logo);
            }
        }

        $outputPath = 'branded/'.$businessId.'/'.md5($sourceAssetUrl).'-'.$destination.'.jpg';
        Storage::disk('local')->makeDirectory('branded/'.$businessId);

        ob_start();
        imagejpeg($canvas, null, 90);
        $outBytes = ob_get_clean();
        Storage::disk('local')->put($outputPath, $outBytes);
        imagedestroy($canvas);

        $overlayLayer = [
            'colour' => $card->accent_color,
            'badge' => $card->badge_text,
            'logo_present' => $logoPresent,
            'width' => $width,
            'height' => $height,
        ];

        $media = BrandedMedia::create([
            'business_id' => $businessId,
            'source_asset_url' => $sourceAssetUrl,
            'license_source' => $licenseSource,
            'output_media_url' => '',
            'overlay_layer' => $overlayLayer,
            'destination' => $destination,
        ]);

        $outputUrl = URL::temporarySignedRoute(
            'x-189.media',
            now()->addDays(7),
            ['business' => $businessId, 'branded' => $media->id]
        );
        $media->update(['output_media_url' => $outputUrl]);

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
