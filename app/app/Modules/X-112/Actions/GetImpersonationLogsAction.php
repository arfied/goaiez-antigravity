<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Models\ImpersonationLog;
use Illuminate\Support\Collection;

final class GetImpersonationLogsAction
{
    public function handle(int $businessId): Collection
    {
        return ImpersonationLog::where('business_id', $businessId)->get();
    }
}
