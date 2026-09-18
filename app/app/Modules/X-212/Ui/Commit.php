<?php

declare(strict_types=1);

namespace App\Modules\X212\Ui;

use App\Modules\X212\Models\MigrationRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Migration Commit'])]
class Commit extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();
        $runs = MigrationRun::where('business_id', $businessId)->orderByDesc('id')->get();

        return view('x-212::commit', ['runs' => $runs]);
    }
}
