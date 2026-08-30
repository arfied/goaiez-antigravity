<?php

declare(strict_types=1);

namespace App\Modules\X130\Actions;

use App\Modules\X130\Models\DemandSeries;
use Illuminate\Database\Eloquent\Collection;

final class DemandQueryAction
{
    /**
     * Queries published demand series for a region.
     * Unpublished cells (< min source count) are omitted.
     */
    public function queryPublishedSeries(int $regionId): Collection
    {
        return DemandSeries::where('region_id', $regionId)
            ->where('is_published', true)
            ->orderBy('period_date', 'asc')
            ->get();
    }
}
