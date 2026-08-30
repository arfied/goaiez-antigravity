<?php

declare(strict_types=1);

namespace App\Modules\X121\Queries;

use Illuminate\Support\Facades\DB;

final class EntityQuery
{
    public function find(string $table, int $id, int $businessId): ?array
    {
        $col = ($table === 'businesses') ? 'id' : 'business_id';
        $row = DB::table($table)
            ->where($col, $businessId)
            ->where('id', $id)
            ->first();

        return $row ? (array) $row : null;
    }
}
