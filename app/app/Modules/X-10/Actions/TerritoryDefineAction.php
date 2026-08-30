<?php

declare(strict_types=1);

namespace App\Modules\X10\Actions;

use App\Modules\X10\Events\TerritoryChanged;
use App\Modules\X10\Models\Territory;
use Illuminate\Support\Facades\Event;

final class TerritoryDefineAction
{
    public function handle(
        int $businessId,
        string $name,
        ?int $assignedStaffId = null,
        ?array $polygonGeojson = null,
        ?array $zipCodes = null
    ): Territory {
        $territory = Territory::create([
            'business_id' => $businessId,
            'name' => $name,
            'assigned_staff_id' => $assignedStaffId,
            'polygon_geojson' => $polygonGeojson,
            'zip_codes' => $zipCodes,
        ]);

        Event::dispatch(new TerritoryChanged($businessId, $territory->id));

        return $territory;
    }
}
