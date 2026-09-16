<?php

declare(strict_types=1);

namespace App\Modules\X137\Ui;

use App\Modules\X137\Models\CallToken;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'DNI pool usage'])]
class DniPoolUtilisation extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $numbers = ($this->businessId > 0) ? DB::table('dni_pool_numbers')->where('business_id', $this->businessId)->orderBy('phone_number')->pluck('phone_number') : collect();
        $active = ($this->businessId > 0) ? CallToken::where('business_id', $this->businessId)->where('status', 'active')->count() : 0;
        $fallback = ($this->businessId > 0) ? DB::table('dni_pool_settings')->where('business_id', $this->businessId)->value('fallback_number') : null;
        $poolSize = $numbers->count();
        $rate = $poolSize > 0 ? (int) round($active / $poolSize * 100) : 0;

        return view('x-137::dni-pool-utilisation', compact('numbers', 'active', 'fallback', 'poolSize', 'rate'));
    }
}
