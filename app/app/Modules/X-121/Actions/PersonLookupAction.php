<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Models\Person;

final class PersonLookupAction
{
    public function idForPhone(int $businessId, string $phone): ?int
    {
        return Person::where('business_id', $businessId)
            ->where('phone', $phone)
            ->value('id');
    }
}
