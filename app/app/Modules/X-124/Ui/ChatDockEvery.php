<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Actions\AssistantAskAction;
use App\Support\Tenancy;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ChatDockEvery extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public string $sessionToken = '';

    public string $utterance = '';

    public array $turns = [];

    public function mount(int $businessId = 0, string $sessionToken = ''): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        $this->sessionToken = $sessionToken ?: bin2hex(random_bytes(16));
    }

    public function ask(AssistantAskAction $askAction): void
    {
        if (trim($this->utterance) === '') {
            return;
        }

        if ($this->businessId === 0) {
            $this->turns[] = [
                'utterance' => $this->utterance,
                'response' => 'I can\'t do that here.',
                'status' => 'unsupported',
            ];
            $this->utterance = '';

            return;
        }

        $res = $askAction->handle($this->businessId, $this->sessionToken, $this->utterance);

        $this->turns[] = [
            'utterance' => $this->utterance,
            'response' => $res['response'] ?? '',
            'status' => $res['status'] ?? 'unsupported',
        ];

        $this->utterance = '';
    }

    public function render()
    {
        return view('x-124::chat-dock-every');
    }
}
