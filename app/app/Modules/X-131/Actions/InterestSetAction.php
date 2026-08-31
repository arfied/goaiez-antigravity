<?php

declare(strict_types=1);

namespace App\Modules\X131\Actions;

use App\Modules\X131\Events\InterestOverridden;
use App\Modules\X131\Models\PersonInterest;
use Illuminate\Support\Facades\Event;

final class InterestSetAction
{
    /**
     * Explicitly sets a tenant-defined interest preference on a Person.
     */
    public function set(int $businessId, int $personId, string $topic): PersonInterest
    {
        $interest = PersonInterest::updateOrCreate(
            ['business_id' => $businessId, 'person_id' => $personId, 'topic' => $topic],
            [
                'confidence_rate' => 1.000,
                'source' => 'tenant_manual',
                'is_tenant_set' => true, // Protected against inference overwrite (TEST ANCHOR)
            ]
        );

        Event::dispatch(new InterestOverridden($businessId, $personId, $topic));

        return $interest;
    }
}
