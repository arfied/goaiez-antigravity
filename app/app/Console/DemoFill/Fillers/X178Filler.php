<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X178\Models\DesignChange;

class X178Filler implements DemoFiller
{
    public const MARKER = 'demo·';

    public function module(): string
    {
        return 'X-178';
    }

    public function fill(Business $business): int
    {
        if (DesignChange::where('business_id', $business->id)->where('block_ref', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        echo "creating\n";
        DesignChange::create([
            'business_id' => $business->id,
            'page_id' => 1,
            'change_type' => 'color_token',
            'block_ref' => 'demo·hero_heading',
            'previous_state' => ['color' => '#8a8a8a'],
            'new_state' => ['color' => '#0b3d2e'],
            'contrast_ratio' => 7.80,
            'status' => 'applied',
        ]);

        echo "creating\n";
        DesignChange::create([
            'business_id' => $business->id,
            'page_id' => 1,
            'change_type' => 'block_order',
            'block_ref' => 'demo·services_list',
            'previous_state' => ['position' => 3],
            'new_state' => ['position' => 1],
            'contrast_ratio' => 7.00,
            'status' => 'applied',
        ]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return DesignChange::where('business_id', $business->id)->where('block_ref', 'like', self::MARKER.'%')->delete();
    }
}
