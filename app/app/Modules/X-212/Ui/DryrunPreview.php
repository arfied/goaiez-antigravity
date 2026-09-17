<?php

declare(strict_types=1);

namespace App\Modules\X212\Ui;

use App\Modules\X212\Models\MigrationRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Dry-run preview'])]
class DryrunPreview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render()
    {
        $runs = ($this->businessId > 0)
            ? MigrationRun::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('x-212::dryrun-preview', [
            'runs' => $runs,
        ]);
    }
}
