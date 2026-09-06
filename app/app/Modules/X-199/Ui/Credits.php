<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\CreditTerm;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Credits extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public bool $isSample = false;

    #[Locked]
    public ?string $loadError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId;
    }

    public function render()
    {
        if ($this->businessId === 0) {
            return view('x-199::credits', [
                'limit' => 0,
                'outstanding' => 0,
                'terms' => collect(),
            ]);
        }

        $terms = CreditTerm::where('business_id', $this->businessId)
            ->orderBy('created_at', 'desc')
            ->get();

        $limit = $terms->sum('credit_limit_cents');
        $outstanding = $terms->sum('current_outstanding_cents');

        return view('x-199::credits', [
            'limit' => $limit,
            'outstanding' => $outstanding,
            'terms' => $terms,
        ]);
    }
}
