<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Actions\ViewRenderAction;
use App\Services\Tenant\LocationContext;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'View'])]
class AnyViewIt extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    #[Url]
    public int $viewId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        $this->load();
    }

    #[Locked]
    public string $locationTimezone = '';

    public string $errorMessage = '';

    public bool $ready = false;

    public function load(): void
    {
        $this->errorMessage = '';
        $this->ready = true;

        if ($this->businessId > 0 && $this->viewId > 0) {
            $context = app(LocationContext::class);
            $location = $context->current();

            if ($location === null) {
                $this->errorMessage = 'Your account has no location to render the view in.';
                $this->ready = false;

                return;
            }

            if ($location->timezone === null) {
                $this->errorMessage = 'The location has no timezone set.';
                $this->ready = false;

                return;
            }

            $this->locationTimezone = $location->timezone;
        }
    }

    public function render()
    {
        $viewData = null;

        if ($this->businessId > 0 && $this->viewId > 0 && $this->ready) {
            try {
                $action = app(ViewRenderAction::class);
                $viewData = $action->renderView(
                    $this->businessId,
                    $this->viewId,
                    $this->locationTimezone,
                    null,
                    0
                );
            } catch (\Exception $e) {
                $this->errorMessage = 'Please try again later or contact support if the issue persists.';
            }
        }

        return view('x-194::any-view-it', [
            'viewData' => $viewData,
        ]);
    }
}
