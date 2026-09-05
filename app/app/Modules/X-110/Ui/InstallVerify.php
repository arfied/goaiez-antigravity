<?php

declare(strict_types=1);

namespace App\Modules\X110\Ui;

use App\Models\Location;
use App\Modules\X110\Actions\PixelInstallAction;
use App\Modules\X110\Actions\PixelVerifyAction;
use App\Modules\X110\Models\CwvSample;
use App\Modules\X110\Models\PixelEvent;
use Livewire\Attributes\Locked;
use Livewire\Component;

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

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId;
    }

    public function render()
    {
        $location = Location::where('business_id', $this->businessId)->first();
        $domain = $location && $location->website_url ? parse_url($location->website_url, PHP_URL_HOST) : 'yourdomain.com';
        if (!$domain) {
            $domain = 'yourdomain.com';
        }

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

        return view('x-110::install-verify', [
            'domain' => $domain,
            'verify' => $verify,
            'install' => $install,
            'recentEvents' => $recentEvents,
            'cwv' => $cwv,
        ]);
    }
}
