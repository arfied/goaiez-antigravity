<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Waitlist;

final class BookingReadAction
{
    public function requestsSince(int $businessId, \DateTimeInterface $since, ?\DateTimeInterface $until = null): int
    {
        return Waitlist::where('business_id', $businessId)->where('created_at', '>=', $since)->when($until !== null, fn ($q) => $q->where('created_at', '<', $until))->count();
    }

    public function bookedSince(int $businessId, \DateTimeInterface $since, ?\DateTimeInterface $until = null): int
    {
        return Appointment::where('business_id', $businessId)->whereIn('status', ['booked', 'reminded', 'completed'])->where('created_at', '>=', $since)->when($until !== null, fn ($q) => $q->where('created_at', '<', $until))->count();
    }
}
