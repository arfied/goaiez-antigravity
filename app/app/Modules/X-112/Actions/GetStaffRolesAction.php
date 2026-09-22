<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Models\StaffRole;
use Illuminate\Support\Collection;

final class GetStaffRolesAction
{
    public function handle(int $businessId): Collection
    {
        return StaffRole::where('business_id', $businessId)->get();
    }
}
