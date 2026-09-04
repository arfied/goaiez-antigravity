<?php

declare(strict_types=1);

namespace App\Modules\X194\Ui;

use App\Modules\X194\Actions\ViewRenderAction;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AnyViewIt extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public int $viewId = 0;

    public string $locationTimezone = 'UTC';

    public ?float $jobValue = null;

    public int $jobCount = 0;

    public string $errorMessage = '';

    public bool $ready = false;

    public function load(): void
    {
        $this->ready = true;
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
                    $this->jobValue,
                    $this->jobCount
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
