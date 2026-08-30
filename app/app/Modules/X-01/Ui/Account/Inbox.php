<?php

declare(strict_types=1);

namespace App\Modules\X01\Ui\Account;

use Livewire\Component;

class Inbox extends Component
{
    public array $channels = ['sms', 'email', 'voice', 'chat'];

    public function render()
    {
        return view('x-01::account-inbox', [
            'channels' => $this->channels,
        ]);
    }
}
