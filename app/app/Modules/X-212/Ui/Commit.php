<?php

declare(strict_types=1);

namespace App\Modules\X212\Ui;

use App\Enums\UserRole;
use App\Modules\X212\Actions\MigrationCommitAction;
use App\Modules\X212\Models\MigrationRecord;
use App\Modules\X212\Models\MigrationRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Migration Commit'])]
class Commit extends Component
{
    public ?string $error = null;

    public ?string $success = null;

    public function mount(): void
    {
        abort_unless(Tenancy::check(), 403, 'Your current website works on one business — open it from Tenant locations first.');
    }

    public function commitRun(int $runId): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);

        $this->error = null;
        $this->success = null;

        $businessId = Tenancy::idOrFail();

        $records = MigrationRecord::where('business_id', $businessId)
            ->where('migration_run_id', $runId)
            ->orderBy('record_index')
            ->pluck('raw_data')
            ->all();

        $result = app(MigrationCommitAction::class)->handle($businessId, $runId, $records);

        if ($result['status'] !== 'committed') {
            $this->error = $result['reason'] ?? 'That run cannot be committed.';

            return;
        }

        $this->success = 'Imported '.$result['imported_records'].' of '.count($records).' records. Nothing was sent to any of them.';
    }

    public function render()
    {
        $businessId = Tenancy::idOrFail();
        $runs = MigrationRun::where('business_id', $businessId)->orderByDesc('id')->get();

        return view('x-212::commit', ['runs' => $runs]);
    }
}
