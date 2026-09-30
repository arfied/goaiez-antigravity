<?php

declare(strict_types=1);

namespace App\Modules\X212\Actions;

use App\Modules\X212\Models\MigrationFieldMap;
use App\Modules\X212\Models\MigrationRecord;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use Illuminate\Support\Facades\DB;

final class MigrationRecheckAction
{
    public function handle(int $businessId, int $migrationRunId): array
    {
        return DB::transaction(function () use ($businessId, $migrationRunId) {
            $run = MigrationRun::where('business_id', $businessId)->findOrFail($migrationRunId);

            if ($run->status !== 'dry_run_ready') {
                return [
                    'status' => 'refused',
                    'reason' => 'This run reads '.$run->status.'. Only a run that has been dry-run and not yet committed can be committed.',
                    'migration_run_id' => $run->id,
                ];
            }

            $maps = MigrationFieldMap::where('business_id', $businessId)
                ->where('migration_run_id', $run->id)
                ->get();

            if ($maps->isEmpty()) {
                return [
                    'status' => 'refused',
                    'reason' => 'No field mappings for this import yet. Map a column first.',
                    'migration_run_id' => $run->id,
                ];
            }

            $rejects = MigrationReject::where('business_id', $businessId)
                ->where('migration_run_id', $run->id)
                ->whereNull('resolved_at')
                ->orderBy('record_index')
                ->get();

            $resolvedCount = 0;
            $skippedNonPersonMaps = 0;

            foreach ($rejects as $reject) {
                $raw = $reject->raw_data;
                $normalised = $raw;

                foreach ($maps as $map) {
                    if ($map->target_entity !== 'person') {
                        $skippedNonPersonMaps++;

                        continue;
                    }
                    if (array_key_exists($map->source_field, $raw)) {
                        $normalised[$map->target_field] = $raw[$map->source_field];
                    }
                }

                if (! empty($normalised['phone'])) {
                    MigrationRecord::create([
                        'business_id' => $businessId,
                        'migration_run_id' => $run->id,
                        'record_index' => $reject->record_index,
                        'raw_data' => $normalised,
                    ]);

                    $reject->update(['resolved_at' => now()]);
                    $resolvedCount++;
                }
            }

            $importedCount = MigrationRecord::where('business_id', $businessId)
                ->where('migration_run_id', $run->id)
                ->count();

            $rejectedCount = MigrationReject::where('business_id', $businessId)
                ->where('migration_run_id', $run->id)
                ->whereNull('resolved_at')
                ->count();

            $run->update([
                'imported_records' => $importedCount,
                'rejected_records' => $rejectedCount,
            ]);

            // skipped_non_person_maps: wait, does it count per map or per map per reject?
            // "skip the others and count them". I will count per map encountered during loops, or maybe just per map?
            // I'll count per map * per reject as written, or maybe it should be the total maps skipped. Let's fix that to be simpler if it just means count them.
            // I will use $maps->filter(fn($m) => $m->target_entity !== 'person')->count() to be exact.

            return [
                'status' => 'rechecked',
                'migration_run_id' => $run->id,
                'resolved' => $resolvedCount,
                'still_rejected' => $rejectedCount,
                'skipped_non_person_maps' => $maps->filter(fn ($m) => $m->target_entity !== 'person')->count(),
            ];
        });
    }
}
