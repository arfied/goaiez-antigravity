<?php

declare(strict_types=1);

namespace App\Modules\X156\Actions;

use App\Modules\X156\Models\IngestSource;

final class IngestSourcePauseAction
{
    public function handle(int $businessId, int $sourceId, bool $active): IngestSource
    {
        $source = IngestSource::where('business_id', $businessId)->findOrFail($sourceId);
        $source->is_active = $active;
        $source->save();

        return $source;
    }
}
