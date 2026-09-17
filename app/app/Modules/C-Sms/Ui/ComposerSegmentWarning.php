<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use App\Modules\CSms\Models\SmsComposition;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Message segments'])]
class ComposerSegmentWarning extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $body = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        if ($this->businessId > 0) {
            $texts = SmsComposition::where('business_id', $this->businessId)
                ->orderByDesc('id')
                ->get();
        } else {
            $texts = collect();
        }

        return view('c-sms::composer-segment-warning', [
            'texts' => $texts,
        ]);
    }
}
