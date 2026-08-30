<?php

declare(strict_types=1);

namespace App\Modules\X203\Actions;

use App\Modules\X203\Models\Runbook;
use App\Modules\X203\Models\RunbookRun;

final class DrRunbookRunAction
{
    public function handle(int $businessId, int $runbookId): RunbookRun
    {
        $runbook = Runbook::where('business_id', $businessId)->findOrFail($runbookId);

        return RunbookRun::create([
            'business_id' => $businessId,
            'runbook_id' => $runbook->id,
            'status' => 'completed',
            'executed_steps' => $runbook->steps,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
