<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Models\ReplyCode;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Reply codes'])]
class AlertReplyBy extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $codes = ($this->businessId > 0)
            ? ReplyCode::where('business_id', $this->businessId)->where('is_live', true)->get()
            : collect();

        return view('x-153::alert-reply-by', [
            'codes' => $codes,
        ]);
    }
}
