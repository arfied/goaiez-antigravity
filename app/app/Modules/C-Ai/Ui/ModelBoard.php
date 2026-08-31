<?php

declare(strict_types=1);

namespace App\Modules\CAi\Ui;

use App\Modules\CAi\Models\AiCall;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ModelBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $calls = ($this->businessId > 0)
            ? AiCall::where('business_id', $this->businessId)->orderBy('id', 'desc')->limit(20)->get()
            : collect();

        return view('c-ai::model-board', [
            'calls' => $calls,
        ]);
    }
}
