<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Models\ComplianceRegister;
use Illuminate\Support\Collection;

final class GetRegistersAction
{
    public function handle(int $businessId): Collection
    {
        return ComplianceRegister::where('business_id', $businessId)->get();
    }
}
