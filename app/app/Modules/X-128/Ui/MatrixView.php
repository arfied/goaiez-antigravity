<?php

declare(strict_types=1);

namespace App\Modules\X128\Ui;

use App\Modules\X128\Actions\MatrixGenerateAction;
use App\Modules\X128\Models\IntegrationMatrix;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class MatrixView extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?string $success = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function generateMatrix(MatrixGenerateAction $action): void
    {
        $result = $action->handle(Tenancy::idOrFail());

        $this->success = 'Matrix generated: '.$result['emitters_count'].' events emitted, '.$result['subscribers_count'].' subscribed, '
            .$result['orphans_count'].' with no declared subscriber. The counts below come from this run; nothing outside this screen reads the matrix yet.';
    }

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
