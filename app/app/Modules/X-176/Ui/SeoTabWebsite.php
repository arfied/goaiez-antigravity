<?php

declare(strict_types=1);

namespace App\Modules\X176\Ui;

use App\Modules\X176\Models\SchemaSnapshot;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Schema status'])]
class SeoTabWebsite extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $schemas = ($this->businessId > 0)
            ? SchemaSnapshot::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-176::seo-tab-website', [
            'schemas' => $schemas,
        ]);
    }
}
