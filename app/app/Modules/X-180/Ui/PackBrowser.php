<?php

declare(strict_types=1);

namespace App\Modules\X180\Ui;

use App\Modules\X180\Models\ContentPack;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Starter content for your industry'])]
class PackBrowser extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $packs = ContentPack::where('business_id', $this->businessId)->orderBy('pack_name')->get();

        return view('x-180::pack-browser', [
            'packs' => $packs,
        ]);
    }
}
