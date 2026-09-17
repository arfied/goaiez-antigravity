<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui;

use App\Models\Conversation;
use App\Models\Message;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Activity'])]
class History extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();

        $ids = Conversation::where('business_id', $businessId)->pluck('id');
        $messages = Message::whereIn('conversation_id', $ids)->latest('created_at')->latest('id')->get();

        return view('x-01::history', ['messages' => $messages]);
    }
}
