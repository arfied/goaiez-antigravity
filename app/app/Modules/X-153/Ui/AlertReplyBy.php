<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Models\ReplyCode;
use Livewire\Component;

class AlertReplyBy extends Component
{
    public int $businessId = 0;

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
