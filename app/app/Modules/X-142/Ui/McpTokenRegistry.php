<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Modules\X142\Models\McpToken;
use Livewire\Component;

class McpTokenRegistry extends Component
{
    public function render()
    {
        $tokens = McpToken::orderBy('id', 'desc')->get();

        return view('x-142::mcp-token-registry', [
            'tokens' => $tokens,
        ]);
    }
}
