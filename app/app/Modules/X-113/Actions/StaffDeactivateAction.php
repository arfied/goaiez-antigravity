<?php

declare(strict_types=1);

namespace App\Modules\X113\Actions;

use App\Modules\X113\Events\StaffDeactivated;
use App\Modules\X113\Models\StaffUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;

final class StaffDeactivateAction
{
    public function handle(int $businessId, int $staffUserId): StaffUser
    {
        $staff = StaffUser::where('business_id', $businessId)->findOrFail($staffUserId);
        $staff->update([
            'is_active' => false,
            'deactivated_at' => Carbon::now(),
        ]);

        Event::dispatch(new StaffDeactivated($businessId, $staff->id, $staff->email));

        return $staff;
    }
}
