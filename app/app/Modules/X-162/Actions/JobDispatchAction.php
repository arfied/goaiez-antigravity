<?php

declare(strict_types=1);

namespace App\Modules\X162\Actions;

use App\Modules\X162\Events\JobDispatched;
use App\Modules\X162\Models\DispatchAssignment;
use Illuminate\Support\Facades\Event;

final class JobDispatchAction
{
    public function handle(int $businessId, int $jobId, int $techId): DispatchAssignment
    {
        $assignment = DispatchAssignment::create([
            'business_id' => $businessId,
            'job_id' => $jobId,
            'tech_id' => $techId,
            'status' => 'dispatched',
        ]);

        Event::dispatch(new JobDispatched($businessId, $jobId, $techId));

        return $assignment;
    }
}
