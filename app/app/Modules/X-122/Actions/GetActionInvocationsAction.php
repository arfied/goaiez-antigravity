<?php

declare(strict_types=1);

namespace App\Modules\X122\Actions;

use App\Modules\X122\Models\ActionInvocation;
use Illuminate\Pagination\LengthAwarePaginator;

final class GetActionInvocationsAction
{
    public function handle(int $businessId, string $search = ''): LengthAwarePaginator
    {
        $query = ActionInvocation::query()->where('business_id', $businessId);

        if ($search !== '') {
            $query->where('action_name', 'like', '%'.$search.'%');
        }

        return $query
            ->orderByRaw("CASE WHEN status = 'refused' THEN 0 ELSE 1 END ASC")
            ->orderBy('id', 'desc')
            ->paginate(15);
    }
}
