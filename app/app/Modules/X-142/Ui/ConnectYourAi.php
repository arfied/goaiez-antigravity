<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Models\McpToken;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ConnectYourAi extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
    }

    public function render(): View
    {
        return view('x-142::connect-your-ai', [
            'connections' => McpToken::where('is_revoked', false)->orderBy('id', 'desc')->get(),
        ]);
    }
}
