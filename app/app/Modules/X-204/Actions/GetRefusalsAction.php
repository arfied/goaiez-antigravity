<?php

declare(strict_types=1);

namespace App\Modules\X204\Actions;

use App\Modules\X204\Models\SendPermit;
use Illuminate\Support\Collection;

final class GetRefusalsAction
{
    public function handle(int $businessId): Collection
    {
        return SendPermit::where('business_id', $businessId)->where('permit_status', 'refused')->get();
    }
}
