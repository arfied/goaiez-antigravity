<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Models\Location;
use App\Modules\X110\Actions\PixelInstallAction;
use App\Modules\X110\Actions\PixelVerifyAction;
use App\Modules\X110\Models\CwvSample;
use App\Modules\X110\Models\PixelEvent;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout')]
class InstallVerify extends Component
{
    #[Locked]
    public int $businessId = 0;

    #[Locked]
    public bool $isSample = false;

    #[Locked]
    public string $servedDomain = 'cdn.external-tracker.com';

    #[Locked]
    public bool $thirdPartyCookiesDisabled = true;

    public bool $showEvents = false;

    public function toggleEvents(): void
    {
        $this->showEvents = ! $this->showEvents;
    }

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $location = Location::where('business_id', $this->businessId)->first();
        $domain = $location && $location->website_url ? parse_url($location->website_url, PHP_URL_HOST) : null;

        $isEmpty = false;
        if (! $domain) {
            $isEmpty = true;
            $domain = 'yourdomain.com';
        }

        try {
            $verify = app(PixelVerifyAction::class)->handle($this->businessId, $this->servedDomain, $this->thirdPartyCookiesDisabled);
            $install = app(PixelInstallAction::class)->handle($this->businessId, $domain);

            $recentEvents = PixelEvent::where('business_id', $this->businessId)
                ->where('created_at', '>=', now()->subMinute())
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            $cwv = CwvSample::where('business_id', $this->businessId)
                ->latest('created_at')
                ->first();
        } catch (\Exception $e) {
            return view('x-110::install-verify', ['loadError' => $e->getMessage()]);
        }

        return view('x-110::install-verify', [
            'domain' => $domain,
            'verify' => $verify,
            'install' => $install,
            'recentEvents' => $recentEvents,
            'cwv' => $cwv,
            'loadError' => null,
            'isEmpty' => $isEmpty,
        ]);
    }
}
