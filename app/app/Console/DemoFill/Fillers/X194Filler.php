<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X194\Models\SavedView;

class X194Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-194';
    }

    public function fill(Business $business): int
    {
        if (SavedView::where('business_id', $business->id)->where('view_name', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        SavedView::create([
            'business_id' => $business->id,
            'view_name' => self::MARKER.'Open jobs',
            'view_type' => 'table',
            'filter_config' => ['status' => 'open'],
            'columns_config' => ['name', 'status'],
            'is_default' => false,
        ]);
        SavedView::create([
            'business_id' => $business->id,
            'view_name' => self::MARKER.'This week',
            'view_type' => 'table',
            'filter_config' => ['range' => 'week'],
            'columns_config' => ['name', 'status'],
            'is_default' => false,
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        $count = SavedView::where('business_id', $business->id)->where('view_name', 'like', self::MARKER.'%')->delete();

        return $count;
    }
}
