<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use App\Modules\CSms\Models\SmsComposition;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Text thread'])]
class Thread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $messages = ($this->businessId > 0)
            ? SmsComposition::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('c-sms::thread', [
            'messages' => $messages,
        ]);
    }
}
