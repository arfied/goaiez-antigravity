<?php

declare(strict_types=1);

namespace App\Modules\X220\Ui;

use App\Modules\X220\Models\AiPrompt;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PromptHistory extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

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
