<?php

declare(strict_types=1);

namespace App\Modules\X212\Actions;

use App\Modules\X121\Actions\PersonUpsertAction;
use App\Modules\X212\Events\MigrationCommitted;
use App\Modules\X212\Models\MigrationRun;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class MigrationCommitAction
{
    public function handle(int $businessId, int $migrationRunId, array $records): array
    {
        return DB::transaction(function () use ($businessId, $migrationRunId, $records) {
            $run = MigrationRun::where('business_id', $businessId)->findOrFail($migrationRunId);

            $committedCount = 0;
            foreach ($records as $record) {
                if (! empty($record['phone'])) {
                    app(PersonUpsertAction::class)->upsertByPhone(
                        $businessId,
                        $record['phone'],
                        [
                            'first_name' => $record['first_name'] ?? 'Imported Customer',
                            'email' => $record['email'] ?? null,
                        ]
                    );
                    $committedCount++;
                }
            }

            $run->update([
                'status' => 'committed',
                'imported_records' => $committedCount,
            ]);

            Event::dispatch(new MigrationCommitted($businessId, $run->id, $committedCount));

            return [
                'status' => 'committed',
                'migration_run_id' => $run->id,
                'imported_records' => $committedCount,
                'is_silent_mode' => true,
            ];
        });
    }
}
