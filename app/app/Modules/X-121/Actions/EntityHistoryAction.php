<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Models\EntityHistoryRecord;

final class EntityHistoryAction
{
    public function handle(string $table, int $id, int $businessId): array
    {
        return EntityHistoryRecord::where('business_id', $businessId)
            ->where('entity_type', $table)
            ->where('entity_id', $id)
            ->orderBy('version', 'desc')
            ->get()
            ->toArray();
    }
}
