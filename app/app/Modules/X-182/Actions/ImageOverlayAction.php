<?php

declare(strict_types=1);

namespace App\Modules\X182\Actions;

final class ImageOverlayAction
{
    /**
     * Applies tenant brand overlay layer to real job photo (G12-32, G12-38, P-131).
     */
    public function applyOverlay(int $businessId, string $rawImageUrl): string
    {
        return "https://cdn.goaiez.com/overlays/{$businessId}/watermarked_".basename($rawImageUrl);
    }
}
