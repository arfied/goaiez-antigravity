<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Models\Conversation;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Thread extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        if ($this->businessId > 0) {
            Tenancy::set($this->businessId);
        }

        $conversations = ($this->businessId > 0)
            ? Conversation::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-01::thread', [
            'conversations' => $conversations,
        ]);
    }
}
