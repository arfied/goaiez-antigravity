<?php

declare(strict_types=1);

namespace App\Modules\X203\Actions;

use App\Modules\X203\Events\DrRestoreExecuted;
use Illuminate\Support\Facades\Event;

final class DrPitrAction
{
    public function handle(int $businessId, string $backupId, string $targetTimestamp): array
    {
        Event::dispatch(new DrRestoreExecuted($businessId, $backupId, $targetTimestamp));

        return [
            'status' => 'pitr_restored',
            'target_timestamp' => $targetTimestamp,
            'backup_id' => $backupId,
        ];
    }
}
