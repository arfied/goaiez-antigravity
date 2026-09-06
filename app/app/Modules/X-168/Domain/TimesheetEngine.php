<?php

declare(strict_types=1);

namespace App\Modules\X168\Domain;

use DomainException;

final class TimesheetEngine
{
    public function assertJobBound(?int $jobId, string $type): void
    {
        if ($type === 'attendance' || $type === 'overtime' || $type === 'out_of_hours') {
            throw new DomainException('Refused: X-168 JobTime has no overtime, out-of-hours, or attendance (P-204).');
        }
        if ($jobId === null) {
            throw new DomainException('Refused: GPS clock-in is bound to job state, not a timesheet.');
        }
    }
}
