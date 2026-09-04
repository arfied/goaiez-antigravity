<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Modules\X110\Models\PixelEvent;
use App\Modules\X110\Models\Visit;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Today extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[\Livewire\Attributes\Locked]
    public bool $isSample = false;

    #[\Livewire\Attributes\Locked]
    public ?string $loadError = null;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId;
    }

    public function render()
    {
        if ($this->businessId === 0) {
            return view('x-110::today', [
                'isVerified' => false,
                'todayVisitsCount' => 0,
            ]);
        }

        $isVerified = PixelEvent::where('business_id', $this->businessId)->exists();

        $todayVisitsCount = Visit::where('business_id', $this->businessId)
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return view('x-110::today', [
            'isVerified' => $isVerified,
            'todayVisitsCount' => $todayVisitsCount,
        ]);
    }
}
