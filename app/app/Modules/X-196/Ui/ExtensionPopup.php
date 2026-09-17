<?php

declare(strict_types=1);

namespace App\Modules\X196\Ui;

use App\Modules\X196\Actions\ExtensionInjectAction;
use App\Modules\X196\Actions\ExtensionScanAction;
use App\Modules\X196\Models\ExtensionSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Browser extension'])]
class ExtensionPopup extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId > 0 ? $businessId : (Tenancy::id() ?? 0);
        if ($this->businessId <= 0) {
            abort(403, 'Tenant context is required');
        }
    }

    public function scanPage(int $sessionId, string $pageUrl, string $pageDomContent, ExtensionScanAction $action): void
    {
        $action->scanPage($this->businessId, $sessionId, $pageUrl, $pageDomContent);
    }

    public function injectProspect(int $sessionId, string $sourceUrl, string $attestationId, array $prospectPayload, ExtensionInjectAction $action): void
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
