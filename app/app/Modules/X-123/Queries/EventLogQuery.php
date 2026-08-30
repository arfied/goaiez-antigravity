<?php

declare(strict_types=1);

namespace App\Modules\X123\Queries;

use App\Modules\X123\Models\DeadLetter;
use App\Modules\X123\Models\EventLog;

final class EventLogQuery
{
    public function listEvents(int $businessId, int $limit = 50): array
    {
        return EventLog::where('business_id', $businessId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function listDeadLetters(int $businessId): array
    {
        return DeadLetter::where('business_id', $businessId)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();
    }
}
