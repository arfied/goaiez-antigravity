<?php

declare(strict_types=1);

namespace App\Modules\X138\Ui;

use App\Modules\X138\Actions\SiteResultsAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Campaign ROI Dashboard'])]
class RoiDashboard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $snapshots = ($this->businessId > 0)
            ? DB::table('roi_snapshots')->where('business_id', $this->businessId)->get()
            : collect();

        $results = $this->businessId > 0 ? app(SiteResultsAction::class)->handle($this->businessId) : null;

        return view('x-138::roi-dashboard', [
            'snapshots' => $snapshots,
            'results' => $results,
        ]);
    }
}
