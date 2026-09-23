<?php

declare(strict_types=1);

namespace App\Modules\X203\Ui;

use App\Modules\X203\Actions\DrRestoreTestAction;
use App\Modules\X203\Models\RestoreTest;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Restore tests'])]
class DrDashboard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $backupId = '';

    public string $expectedChecksum = '';

    public string $actualChecksum = '';

    public string $expectedRowCount = '';

    public string $restoredRowCount = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function recordTest(): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->backupId)) {
            $this->error = 'Backup ID is required.';

            return;
        }

        $result = app(DrRestoreTestAction::class)->handle(
            Tenancy::idOrFail(),
            $this->backupId,
            $this->expectedChecksum,
            $this->actualChecksum,
            (int) $this->expectedRowCount,
            (int) $this->restoredRowCount
        );

        $this->success = 'Recorded restore test outcome: '.$result['status'].' ('.$result['reason'].'). This feeds the restoration log lists; nothing downstream is wired to it yet.';
        $this->backupId = '';
        $this->expectedChecksum = '';
        $this->actualChecksum = '';
        $this->expectedRowCount = '';
        $this->restoredRowCount = '';
    }

    public function render()
    {
        $tests = ($this->businessId > 0)
            ? RestoreTest::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-203::dr-dashboard', [
            'tests' => $tests,
        ]);
    }
}
