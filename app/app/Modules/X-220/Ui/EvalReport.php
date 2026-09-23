<?php

declare(strict_types=1);

namespace App\Modules\X220\Ui;

use App\Modules\X220\Actions\EvalRunAction;
use App\Modules\X220\Models\GoldenSet;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EvalReport extends Component
{
    #[Locked]
    public int $businessId = 0;

    public array $lastResults = [];

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        if ($this->businessId === 0) {
            abort(403);
        }
    }

    public function run(int $setId): void
    {
        $set = GoldenSet::where('business_id', $this->businessId)->findOrFail($setId);

        $action = app(EvalRunAction::class);
        $res = $action->handle($this->businessId, $set->prompt_id, $set->id);

        $this->lastResults[$setId] = $res;
    }

    public function render()
    {
        $sets = ($this->businessId > 0)
            ? GoldenSet::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-220::eval-report', [
            'sets' => $sets,
        ]);
    }
}
