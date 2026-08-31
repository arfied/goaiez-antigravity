<?php

declare(strict_types=1);

namespace App\Modules\X142\Ui;

use App\Modules\X142\Models\McpToken;
use Livewire\Attributes\Locked;
use Livewire\Component;

class McpTokenRegistry extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $tokens = ($this->businessId > 0)
            ? McpToken::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-142::mcp-token-registry', [
            'tokens' => $tokens,
        ]);
    }
}
