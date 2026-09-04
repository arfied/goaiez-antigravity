<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Models\SavedView;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SavedViewsList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $errorMessage = '';

    public bool $ready = false;

    public function load(): void
    {
        $this->ready = true;
    }

    public function makeDefault(int $viewId): void
    {
        app(\App\Modules\X194\Actions\SetDefaultViewAction::class)->setDefault($this->businessId, $viewId);
    }

    public function render()
    {
        $action = app(\App\Modules\X194\Actions\ViewListAction::class);
        $views = ($this->businessId > 0 && $this->ready)
            ? $action->listViews($this->businessId)
            : collect();

        return view('x-194::saved-views-list', [
            'views' => $views,
        ]);
    }
}
