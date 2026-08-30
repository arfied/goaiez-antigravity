<?php

declare(strict_types=1);

namespace App\Modules\X114\Actions;

use App\Modules\X114\Events\MediaResized;
use App\Modules\X114\Models\MediaAsset;
use Illuminate\Support\Facades\Event;

final class MediaResizeAction
{
    /**
     * Resizes and transcodes master asset to specific width/height in WebP format (G17-08, G16-20).
     */
    public function resize(int $businessId, int $assetId, int $targetWidth, int $targetHeight): MediaAsset
    {
        $asset = MediaAsset::where('business_id', $businessId)->findOrFail($assetId);

        $resized = MediaAsset::create([
            'business_id' => $businessId,
            'slot_name' => $asset->slot_name.'_'.$targetWidth.'x'.$targetHeight,
            'url' => "https://cdn.goaiez.com/media/{$businessId}/resized_{$targetWidth}x{$targetHeight}.webp",
            'format' => 'webp',
            'width' => $targetWidth,
            'height' => $targetHeight,
            'is_client_upload' => $asset->is_client_upload,
            'is_generated' => $asset->is_generated,
            'tags' => ['transcoded_webp'],
        ]);

        Event::dispatch(new MediaResized($businessId, $resized->id, $targetWidth, $targetHeight));

        return $resized;
    }
}
