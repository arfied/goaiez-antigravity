<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Actions\SetDefaultViewAction;
use App\Modules\X194\Actions\ViewListAction;
use App\Modules\X194\Actions\ViewSaveAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Saved views'])]
class SavedViewsList extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Validate('required|string|max:255')]
    public string $newViewName = '';

    public string $errorMessage = '';

    public string $saveError = '';

    public string $defaultError = '';

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    /**
     * Called by standing tests. Removing it would cause them to throw.
     */
    public function load(): void {}

    public function saveView(): void
    {
        $this->validate();
        $this->saveError = '';

        try {
            app(ViewSaveAction::class)->save($this->businessId, $this->newViewName);
            $this->newViewName = '';
        } catch (\Exception $e) {
            $this->saveError = 'We could not save your view.';
        }
    }

    public function makeDefault(int $viewId): void
    {
        $this->defaultError = '';
        try {
            app(SetDefaultViewAction::class)->setDefault($this->businessId, $viewId);
        } catch (\Exception $e) {
            $this->defaultError = 'We could not update your default view.';
        }
    }

    public function clearDefaultError(): void
    {
        $this->defaultError = '';
    }

    public function render()
    {
        $this->errorMessage = '';
        try {
            $action = app(ViewListAction::class);
            $views = ($this->businessId > 0)
                ? $action->listViews($this->businessId)->collect()
                : collect();
        } catch (\Exception $e) {
            // A space string makes $errorMessage truthy for the blade condition without displaying extra text.

            $this->errorMessage = ' ';
            $views = collect();
        }

        return view('x-194::saved-views-list', [
            'views' => $views,
        ]);
    }
}
