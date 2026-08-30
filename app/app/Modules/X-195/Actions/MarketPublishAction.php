<?php

declare(strict_types=1);

namespace App\Modules\X195\Actions;

use App\Modules\X195\Events\ManifestPublished;
use App\Modules\X195\Models\MarketItem;
use Illuminate\Support\Facades\Event;

final class MarketPublishAction
{
    public function publish(
        int $businessId,
        string $itemName,
        string $itemSlug,
        string $version,
        array $manifestJson,
        bool $isVerified = false
    ): MarketItem {
        $item = MarketItem::updateOrCreate(
            ['business_id' => $businessId, 'item_slug' => $itemSlug],
            [
                'item_name' => $itemName,
                'version' => $version,
                'manifest_json' => $manifestJson,
                'is_verified' => $isVerified,
            ]
        );

        Event::dispatch(new ManifestPublished($businessId, $item->id, $itemSlug, $version));

        return $item;
    }
}
