<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Models\McpToken;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Connect your AI'])]
class ConnectYourAi extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
        $this->businessId = Tenancy::id() ?? 0;
    }

    public function render(): View
    {
        return view('x-142::connect-your-ai', [
            'connections' => $this->businessId > 0 ? McpToken::where('business_id', $this->businessId)->where('is_revoked', false)->orderBy('id', 'desc')->get() : collect(),
        ]);
    }
}
