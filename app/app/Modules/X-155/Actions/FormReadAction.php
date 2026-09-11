<?php

namespace App\Modules\X155\Actions;

use App\Modules\X155\Models\FormDefinition;

class FormReadAction
{
    public function firstIdForBusiness(int $businessId): ?int
    {
        return FormDefinition::where('business_id', $businessId)->orderBy('id')->value('id');
    }
}
