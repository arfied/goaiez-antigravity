<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X167\Models\PurchaseOrder;
use App\Modules\X167\Models\StockItem;
use App\Modules\X167\Models\StockLocation;
use App\Modules\X167\Models\Supplier;

class X167Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-167';
    }

    public function fill(Business $business): int
    {
        if (Supplier::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }
        $supp = Supplier::create(['business_id' => $business->id, 'name' => self::MARKER.'Supplier']);
        PurchaseOrder::create(['business_id' => $business->id, 'supplier_id' => $supp->id, 'po_number' => self::MARKER.'PO1', 'items' => []]);
        PurchaseOrder::create(['business_id' => $business->id, 'supplier_id' => $supp->id, 'po_number' => self::MARKER.'PO2', 'items' => []]);
        $loc = StockLocation::create(['business_id' => $business->id, 'name' => self::MARKER.'Van 1']);
        for ($i = 0; $i < 3; $i++) {
            StockItem::create(['business_id' => $business->id, 'location_id' => $loc->id, 'sku' => self::MARKER."Item$i", 'name' => self::MARKER."Item $i", 'quantity' => 10]);
        }

        return 7;
    }

    public function purge(Business $business): int
    {
        $count = StockItem::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        $count += StockLocation::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();
        $count += PurchaseOrder::where('business_id', $business->id)->where('po_number', 'like', self::MARKER.'%')->delete();
        $count += Supplier::where('business_id', $business->id)->where('name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
