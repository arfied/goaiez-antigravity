<?php

declare(strict_types=1);

namespace App\Modules\X212\Actions;

use App\Modules\X121\Actions\PersonLookupAction;
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

            if ($run->status !== 'dry_run_ready') {
                return [
                    'status' => 'refused',
                    'reason' => 'This run reads '.$run->status.'. Only a run that has been dry-run and not yet committed can be committed.',
                    'migration_run_id' => $run->id,
                ];
            }

            // A blank cell was not given: it never overwrites what the business already has. An existing contact keeps their
            // name unless the file gives one; only a new contact without a name gets the placeholder (an import used to rename
            // every matched contact "Imported Customer" and blank their email when the file had no such column).
            $given = static fn (mixed $v): ?string => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;
            $committedCount = 0;
            foreach ($records as $record) {
                if (! empty($record['phone'])) {
                    $exists = app(PersonLookupAction::class)->idForPhone($businessId, (string) $record['phone']) !== null;
                    app(PersonUpsertAction::class)->upsertByPhone(
                        $businessId,
                        $record['phone'],
                        [
                            'first_name' => $given($record['first_name'] ?? null) ?? ($exists ? null : 'Imported Customer'),
                            'email' => $given($record['email'] ?? null),
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
                'is_silent_mode' => $run->is_silent_mode,
            ];
        });
    }
}
