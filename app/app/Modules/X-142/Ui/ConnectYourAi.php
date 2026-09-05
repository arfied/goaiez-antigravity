<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Modules\X142\Models\McpToken;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ConnectYourAi extends Component
{
    public function render(): View
    {
        return view('x-142::connect-your-ai', [
            'connections' => McpToken::where('is_revoked', false)->orderBy('id', 'desc')->get(),
        ]);
    }
}
