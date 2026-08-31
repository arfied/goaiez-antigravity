<?php

declare(strict_types=1);

namespace App\Modules\X207\Ui;

use App\Modules\X207\Models\PushPrompt;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PromptcopyEditor extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $prompts = ($this->businessId > 0)
            ? PushPrompt::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-207::promptcopy-editor', [
            'prompts' => $prompts,
        ]);
    }
}
