<?php

declare(strict_types=1);

namespace App\Modules\X139\Ui;

use App\Modules\X139\Models\ConversionUpload;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Uploaded Conversions'])]
class ConversionsPushedTile extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $count = 0;
        $valueCents = 0;
        if ($this->businessId > 0) {
            $query = ConversionUpload::where('business_id', $this->businessId)->where('status', 'uploaded');
            $count = $query->count();
            $valueCents = (int) $query->sum('conversion_value_cents');
        }

        return view('x-139::conversions-pushed-tile', [
            'count' => $count,
            'value' => $valueCents / 100,
        ]);
    }
}
