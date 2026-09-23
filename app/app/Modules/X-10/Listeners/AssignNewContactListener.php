<?php

declare(strict_types=1);

namespace App\Modules\X10\Listeners;

use App\Modules\X01\Events\ContactCreated;
use App\Modules\X10\Actions\LeadAssignAction;
use App\Modules\X10\Models\Assignment;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AssignNewContactListener
{
    public function __construct(
        private readonly LeadAssignAction $assignAction,
        private readonly DefaultsRegistry $defaults,
    ) {}

    public function handle(ContactCreated $event): void
    {
        $activeAssignment = Assignment::where('business_id', $event->businessId)
            ->where('lead_id', $event->personId)
            ->where('status', 'active')
            ->first();

        if ($activeAssignment !== null) {
            return;
        }

        $mostRecent = Assignment::where('business_id', $event->businessId)
            ->where('lead_id', $event->personId)
            ->orderBy('created_at', 'desc')
            ->first();

        $previousOwnerStaffId = $mostRecent?->assigned_staff_id;

        $days = $this->defaults->int('routing.workload_window_days');

        $staffWorkloads = Assignment::where('business_id', $event->businessId)
            ->where('status', 'active')
            ->where('created_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('assigned_staff_id')
            ->select('assigned_staff_id', DB::raw('count(*) as count'))
            ->groupBy('assigned_staff_id')
            ->pluck('count', 'assigned_staff_id')
            ->toArray();

        $this->assignAction->handle(
            businessId: $event->businessId,
            leadId: $event->personId,
            previousOwnerStaffId: $previousOwnerStaffId,
            zipCode: null,
            staffWorkloads: $staffWorkloads,
        );
    }
}
