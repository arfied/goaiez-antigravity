<?php

declare(strict_types=1);

namespace App\Modules\X207\Ui;

use App\Enums\UserRole;
use App\Modules\X207\Models\PushPrompt;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Prompt copy'])]
class PromptcopyEditor extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount()
    {
        abort_unless(
            auth()->check() && (auth()->user()->hasRole(UserRole::Owner, UserRole::Manager) || Gate::allows(AdminAccess::GATE)),
            403
        );
        $this->businessId = Tenancy::id();
    }

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
