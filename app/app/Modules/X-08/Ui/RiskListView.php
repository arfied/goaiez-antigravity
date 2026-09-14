<?php

declare(strict_types=1);

namespace App\Modules\X08\Ui;

use App\Enums\UserRole;
use App\Modules\X08\Models\ChurnScore;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Churn risk list'])]
class RiskListView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount()
    {
        abort_unless(auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager)), 403);
        $this->businessId = Tenancy::id();
    }

    public function render()
    {
        $scores = ($this->businessId > 0)
            ? ChurnScore::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-08::risk-list', [
            'scores' => $scores,
        ]);
    }
}
