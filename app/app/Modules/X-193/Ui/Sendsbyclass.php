<?php

declare(strict_types=1);

namespace App\Modules\X193\Ui;

use App\Modules\X193\Models\NotificationClass;
use Livewire\Component;

class Sendsbyclass extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $classes = ($this->businessId > 0)
            ? NotificationClass::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-193::sendsbyclass', [
            'classes' => $classes,
        ]);
    }
}
