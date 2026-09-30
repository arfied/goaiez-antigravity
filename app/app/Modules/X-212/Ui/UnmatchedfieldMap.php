<?php

declare(strict_types=1);

namespace App\Modules\X212\Ui;

use App\Enums\UserRole;
use App\Modules\X212\Actions\MigrationMapFieldAction;
use App\Modules\X212\Actions\MigrationRecheckAction;
use App\Modules\X212\Models\MigrationFieldMap;
use App\Modules\X212\Models\MigrationReject;
use App\Modules\X212\Models\MigrationRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Field mapping'])]
class UnmatchedfieldMap extends Component
{
    public array $targetField = [];

    public ?string $error = null;

    public ?string $success = null;

    public function mapField(int $runId, string $sourceField): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);

        $this->error = null;
        $this->success = null;

        $target = $this->targetField["{$runId}-{$sourceField}"] ?? null;

        if (empty($target)) {
            $this->error = 'Please select a target field.';

            return;
        }

        $businessId = Tenancy::idOrFail();

        app(MigrationMapFieldAction::class)->handle(
            $businessId,
            $runId,
            $sourceField,
            'person',
            $target
        );

        $this->success = "Mapped {$sourceField} to person.{$target}.";
    }

    public function recheck(int $runId): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);

        $this->error = null;
        $this->success = null;

        $businessId = Tenancy::idOrFail();

        $result = app(MigrationRecheckAction::class)->handle($businessId, $runId);

        if ($result['status'] !== 'rechecked') {
            $this->error = $result['reason'] ?? 'Re-check refused.';

            return;
        }

        $this->success = "Resolved {$result['resolved']}, still rejected {$result['still_rejected']}, skipped {$result['skipped_non_person_maps']} non-person maps.";
    }

    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $runIdsWithRejects = MigrationReject::where('business_id', $businessId)
            ->whereNull('resolved_at')
            ->select('migration_run_id')
            ->distinct()
            ->pluck('migration_run_id');

        $runs = MigrationRun::where('business_id', $businessId)
            ->whereIn('id', $runIdsWithRejects)
            ->get();

        $runsData = [];

        foreach ($runs as $run) {
            $rejects = MigrationReject::where('business_id', $businessId)
                ->where('migration_run_id', $run->id)
                ->whereNull('resolved_at')
                ->get();

            $sourceFieldsUnion = [];
            foreach ($rejects as $reject) {
                if (is_array($reject->raw_data)) {
                    foreach (array_keys($reject->raw_data) as $key) {
                        $sourceFieldsUnion[$key] = true;
                    }
                }
            }

            $mappedSourceFields = MigrationFieldMap::where('business_id', $businessId)
                ->where('migration_run_id', $run->id)
                ->pluck('source_field')
                ->toArray();

            $unmapped = array_values(array_diff(array_keys($sourceFieldsUnion), $mappedSourceFields));

            $runsData[] = [
                'run' => $run,
                'unmapped' => $unmapped,
            ];
        }

        $maps = MigrationFieldMap::where('business_id', $businessId)->orderByDesc('id')->get();

        return view('x-212::unmatchedfield-map', [
            'maps' => $maps,
            'runsData' => $runsData,
        ]);
    }
}
