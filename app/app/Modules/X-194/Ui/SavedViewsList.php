<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Actions\SetDefaultViewAction;
use App\Modules\X194\Actions\ViewListAction;
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
        try {
            app(SetDefaultViewAction::class)->setDefault($this->businessId, $viewId);
        } catch (\Exception $e) {
            // Defence in depth: SetDefaultViewAction uses Builder::update() which does not throw in normal conditions, so no test reaches this block.
            $this->errorMessage = 'We could not update your default view.';
        }
    }

    public function render()
    {
        try {
            $action = app(ViewListAction::class);
            $views = ($this->businessId > 0 && $this->ready)
                ? $action->listViews($this->businessId)
                : collect();
        } catch (\Exception $e) {
            // Defence in depth: ViewListAction returns a LazyCollection so the query runs on blade iteration, escaping this try block; thus no test reaches it.
            $this->errorMessage = 'We could not load your saved views.';
            $views = collect();
        }

        return view('x-194::saved-views-list', [
            'views' => $views,
        ]);
    }
}
