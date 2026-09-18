<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantPreviewAction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Action preview'])]
class PreviewCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public string $actionKey = '';

    #[Locked]
    public array $params = [];

    public bool $ready = false;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        abort_if($this->businessId === 0, 404);
    }

    public function load(): void
    {
        $this->ready = true;
    }

    public function render(AssistantPreviewAction $previewAction)
    {
        if (! $this->ready) {
            return view('x-124::preview-card', ['preview' => null]);
        }

        $preview = $previewAction->handle($this->businessId, $this->actionKey, $this->params);

        return view('x-124::preview-card', [
            'preview' => $preview,
        ]);
    }
}
