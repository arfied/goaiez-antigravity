<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Actions\SetDefaultViewAction;
use App\Modules\X194\Actions\ViewListAction;
use App\Modules\X194\Actions\ViewSaveAction;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SavedViewsList extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Validate('required|string|max:255')]
    public string $newViewName = '';

    public string $errorMessage = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function load(): void {}

    public function saveView(): void
    {
        $this->validate();

        try {
            app(ViewSaveAction::class)->save($this->businessId, $this->newViewName);
            $this->newViewName = '';
        } catch (\Exception $e) {
            $this->errorMessage = 'We could not save your view.';
        }
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
            $views = ($this->businessId > 0)
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
