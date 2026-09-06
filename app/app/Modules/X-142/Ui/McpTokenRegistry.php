<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Enums\UserRole;
use App\Modules\X142\Models\McpToken;
use Livewire\Component;

class McpTokenRegistry extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner, UserRole::Manager, UserRole::SuperAdmin), 403);
    }

    public function render()
    {
        $tokens = McpToken::orderBy('id', 'desc')->get();

        return view('x-142::mcp-token-registry', [
            'tokens' => $tokens,
        ]);
    }
}
