<?php

declare(strict_types=1);

namespace App\Modules\X208\Ui;

use App\Modules\X208\Models\MailPiece;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PiecePreview extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $pieces = ($this->businessId > 0)
            ? MailPiece::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-208::piece-preview', [
            'pieces' => $pieces,
        ]);
    }
}
