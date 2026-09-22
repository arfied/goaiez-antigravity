<?php

declare(strict_types=1);

namespace App\Modules\X167\Actions;

use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
use InvalidArgumentException;

final class StockItemCreateAction
{
    public function handle(
        int $businessId,
        string $name,
        string $sku,
        string $van = '',
        string $unit = 'units',
        float $quantity = 0.0,
        float $reorderPoint = 5.0,
    ): StockItem {
        $name = trim($name);
        $sku = trim($sku);

        if ($name === '' || $sku === '') {
            throw new InvalidArgumentException('An item needs a name and a SKU.');
        }

        $location = null;
        $van = trim($van);
        if ($van !== '') {
            $location = StockLocation::firstOrCreate(
                ['business_id' => $businessId, 'name' => $van],
                ['type' => 'van']
            );
        }

        return StockItem::create([
            'business_id' => $businessId,
            'location_id' => $location?->id,
            'name' => $name,
            'sku' => $sku,
            'unit' => $unit,
            'quantity' => $quantity,
            'reorder_point' => $reorderPoint,
        ]);
    }
}
