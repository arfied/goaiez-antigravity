<?php

declare(strict_types=1);

namespace App\Modules\X167\Actions;

use App\Modules\X167\Models\StockItem;
use InvalidArgumentException;

final class StockItemCreateAction
{
    public function handle(
        int $businessId,
        string $name,
        string $sku,
        string $unit = 'units',
        float $quantity = 0.0,
        float $reorderPoint = 5.0,
    ): StockItem {
        $name = trim($name);
        $sku = trim($sku);

        if ($name === '' || $sku === '') {
            throw new InvalidArgumentException('An item needs a name and a SKU.');
        }

        // No location ID: [G6-51] asserts no path under this module writes that column
        // (tests/Modules/X-167/X167Test.php:288). An item is created unassigned and the
        // screen groups it under "No van".
        return StockItem::create([
            'business_id' => $businessId,
            'name' => $name,
            'sku' => $sku,
            'unit' => $unit,
            'quantity' => $quantity,
            'reorder_point' => $reorderPoint,
        ]);
    }
}
