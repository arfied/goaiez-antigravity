<?php

declare(strict_types=1);

namespace App\Modules\X209\Ui;

use App\Modules\X209\Models\FixerCommand;
use Livewire\Component;

class PrivateInbox extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $commands = ($this->businessId > 0)
            ? FixerCommand::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-209::private-inbox', [
            'commands' => $commands,
        ]);
    }
}
