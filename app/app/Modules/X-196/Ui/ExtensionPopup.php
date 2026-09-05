<?php

declare(strict_types=1);

namespace App\Modules\X196\Ui;

use App\Modules\X196\Models\ExtensionSession;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ExtensionPopup extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(): void
    {
        $this->businessId = \App\Support\Tenancy::id() ?? 0;
        if ($this->businessId <= 0) {
            abort(403, 'Tenant context is required');
        }
    }

    public function scanPage(int $sessionId, string $pageUrl, string $pageDomContent, \App\Modules\X196\Actions\ExtensionScanAction $action): void
    {
        $action->scanPage($this->businessId, $sessionId, $pageUrl, $pageDomContent);
    }

    public function injectProspect(int $sessionId, string $sourceUrl, string $attestationId, array $prospectPayload, \App\Modules\X196\Actions\ExtensionInjectAction $action): void
    {
        $action->injectProspect($this->businessId, $sessionId, $sourceUrl, $attestationId, $prospectPayload);
    }

    public function render()
    {
        $sessions = ($this->businessId > 0)
            ? ExtensionSession::where('business_id', $this->businessId)->with('injections')->get()
            : collect();

        return view('x-196::extension-popup', [
            'sessions' => $sessions,
        ]);
    }
}
