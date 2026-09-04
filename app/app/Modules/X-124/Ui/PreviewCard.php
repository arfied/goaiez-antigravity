<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantPreviewAction;
use Exception;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PreviewCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public string $actionKey = '';

    #[Locked]
    public array $params = [];

    public bool $ready = false;

    public ?string $errorMessage = null;

    public function load(): void
    {
        $this->ready = true;
        $this->errorMessage = null;
    }

    public function render(AssistantPreviewAction $previewAction)
    {
        if (! $this->ready) {
            return view('x-124::preview-card', ['preview' => null]);
        }

        try {
            $preview = $previewAction->handle($this->businessId, $this->actionKey, $this->params);
        } catch (Exception $e) {
            $this->errorMessage = 'Failed to load preview';
            $preview = null;
        }

        return view('x-124::preview-card', [
            'preview' => $preview,
        ]);
    }
}
