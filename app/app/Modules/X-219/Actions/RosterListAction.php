<?php

declare(strict_types=1);

namespace App\Modules\X219\Actions;

use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiModuleAssignment;
use App\Modules\X219\Models\AiProvider;

final class RosterListAction
{
    public function handle(int $businessId): array
    {
        return [
            'providers' => AiProvider::where('business_id', $businessId)->get()->toArray(),
            'models' => AiModel::where('business_id', $businessId)->get()->toArray(),
            'assignments' => AiModuleAssignment::where('business_id', $businessId)->get()->toArray(),
        ];
    }
}
