<?php

declare(strict_types=1);

namespace App\Modules\X141\Actions;

use App\Modules\X141\Models\Counterfactual;
use App\Modules\X141\Models\ReplayRun;

final class ReplayQueryAction
{
    public function queryRun(int $businessId, int $runId): array
    {
        $run = ReplayRun::where('business_id', $businessId)->findOrFail($runId);
        $counterfactuals = Counterfactual::where('business_id', $businessId)
            ->where('replay_run_id', $runId)
            ->get();

        return [
            'run' => $run,
            'counterfactuals' => $counterfactuals,
        ];
    }
}
