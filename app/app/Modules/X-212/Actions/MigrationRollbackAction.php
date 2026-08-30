<?php

declare(strict_types=1);

namespace App\Modules\X212\Actions;

use App\Modules\X212\Models\MigrationRun;

final class MigrationRollbackAction
{
    public function handle(int $businessId, int $migrationRunId): array
    {
        $run = MigrationRun::where('business_id', $businessId)->findOrFail($migrationRunId);
        $run->update(['status' => 'rolled_back']);

        return [
            'status' => 'rolled_back',
            'migration_run_id' => $run->id,
        ];
    }
}
