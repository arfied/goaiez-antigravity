<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Waitlist;

final class BookingReadAction
{
    public function requestsSince(int $businessId, \DateTimeInterface $since): int
    {
        return Waitlist::where('business_id', $businessId)->where('created_at', '>=', $since)->count();
    }

    public function bookedSince(int $businessId, \DateTimeInterface $since): int
    {
        return Appointment::where('business_id', $businessId)->whereIn('status', ['booked', 'reminded', 'completed'])->where('created_at', '>=', $since)->count();
    }
}
