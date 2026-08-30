<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Modules\X121\Models\Conversation;
use Livewire\Component;

class Thread extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $conversations = ($this->businessId > 0)
            ? Conversation::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-01::thread', [
            'conversations' => $conversations,
        ]);
    }
}
