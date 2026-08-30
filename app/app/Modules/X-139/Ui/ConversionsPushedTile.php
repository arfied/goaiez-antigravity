<?php

declare(strict_types=1);

namespace App\Modules\X139\Ui;

use App\Modules\X139\Models\ConversionUpload;
use Livewire\Component;

class ConversionsPushedTile extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $count = ($this->businessId > 0)
            ? ConversionUpload::where('business_id', $this->businessId)->where('status', 'uploaded')->count()
            : 0;

        return view('x-139::conversions-pushed-tile', [
            'count' => $count,
        ]);
    }
}
