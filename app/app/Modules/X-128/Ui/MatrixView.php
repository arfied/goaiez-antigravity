<?php

declare(strict_types=1);

namespace App\Modules\X128\Ui;

use App\Modules\X128\Models\IntegrationMatrix;
use Livewire\Component;

class MatrixView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $matrix = ($this->businessId > 0)
            ? IntegrationMatrix::where('business_id', $this->businessId)->orderBy('id', 'desc')->first()
            : null;

        return view('x-128::matrix-view', [
            'matrix' => $matrix,
        ]);
    }
}
