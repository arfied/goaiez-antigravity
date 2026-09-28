<?php

declare(strict_types=1);

namespace App\Modules\X138\Ui;

use App\Modules\X138\Actions\PageEarningsAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Page Earnings'])]
class AttributionRow extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $queries = ($this->businessId > 0)
            ? DB::table('attribution_queries')->where('business_id', $this->businessId)->get()
            : collect();

        // Let's also fetch ROI snapshots if relevant, or maybe it's not strictly needed for the row,
        // but the brief says "X-138 owns_table = attribution_queries · roi_snapshots, reads_table = []. Both, and nothing else. ... what this screen may render is what X-138's own two tables hold."
        $snapshots = ($this->businessId > 0)
            ? DB::table('roi_snapshots')->where('business_id', $this->businessId)->get()
            : collect();

        return view('x-138::attribution-row', [
            'queries' => $queries,
            'snapshots' => $snapshots,
            'earnings' => $this->businessId > 0 ? app(PageEarningsAction::class)->handle($this->businessId) : null,
        ]);
    }
}
