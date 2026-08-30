<?php

declare(strict_types=1);

namespace App\Modules\X220\Ui;

use App\Modules\X220\Models\AiPrompt;
use Livewire\Component;

class PromptHistory extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $prompts = ($this->businessId > 0)
            ? AiPrompt::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-220::prompt-history', [
            'prompts' => $prompts,
        ]);
    }
}
