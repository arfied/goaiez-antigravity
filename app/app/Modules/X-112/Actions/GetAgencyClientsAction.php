<?php

declare(strict_types=1);

namespace App\Modules\X112\Actions;

use App\Modules\X112\Models\AgencyClient;
use Illuminate\Support\Collection;

final class GetAgencyClientsAction
{
    public function handle(int $businessId): Collection
    {
        return AgencyClient::where('business_id', $businessId)->get();
    }
}
