<?php

declare(strict_types=1);

namespace App\Modules\X154\Ui;

use App\Modules\X154\Models\TenantLexicon;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ReadbackScreen extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $lexicons = ($this->businessId > 0)
            ? TenantLexicon::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-154::readback-screen', [
            'lexicons' => $lexicons,
        ]);
    }
}
