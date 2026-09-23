<?php

declare(strict_types=1);

namespace App\Modules\X220\Ui;

use App\Modules\X220\Actions\PromptFreezeAction;
use App\Modules\X220\Models\AiPrompt;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Prompt history'])]
class PromptHistory extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
    }

    public function freeze(int $promptId): void
    {
        app(PromptFreezeAction::class)->handle($this->businessId, $promptId);
    }

    public function render()
    {
        $prompts = ($this->businessId > 0)
            ? AiPrompt::where('business_id', $this->businessId)->withCount('calls')->orderBy('id', 'desc')->get()
            : collect();

        return view('x-220::prompt-history', [
            'prompts' => $prompts,
        ]);
    }
}
