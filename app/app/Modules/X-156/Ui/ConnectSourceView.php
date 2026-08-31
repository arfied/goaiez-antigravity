<?php

declare(strict_types=1);

namespace App\Modules\X156\Ui;

use App\Modules\X156\Models\IngestSource;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ConnectSourceView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $sources = ($this->businessId > 0)
            ? IngestSource::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-156::connect-source', [
            'sources' => $sources,
        ]);
    }
}
