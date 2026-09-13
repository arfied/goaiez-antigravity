<?php

declare(strict_types=1);

namespace App\Modules\X108\Actions;

use App\Modules\X108\Models\Appointment;

final class AppointmentListAction
{
    public function forBusiness(int $businessId, int $limit = 20): array
    {
        $appointments = Appointment::where('business_id', $businessId)
            ->where('start_time', '>=', now())
            ->orderBy('start_time', 'asc')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($appointments as $apt) {
            $result[] = [
                'service_name' => $apt->service_name,
                'start_time' => $apt->start_time,
                'end_time' => $apt->end_time,
            ];
        }

        return $result;
    }
}
