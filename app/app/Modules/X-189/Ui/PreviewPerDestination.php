<?php

declare(strict_types=1);

namespace App\Modules\X189\Ui;

use App\Modules\X189\Models\BrandedMedia;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Branded media'])]
class PreviewPerDestination extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $media = BrandedMedia::where('business_id', $this->businessId)->orderByDesc('id')->get();

        return view('x-189::preview-per-destination', [
            'media' => $media,
        ]);
    }
}
