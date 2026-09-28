<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Modules\X206\Actions\CredentialForgetAction;
use App\Modules\X206\Actions\CredentialHintAction;
use App\Modules\X206\Actions\CredentialStoreAction;
use App\Modules\X206\Actions\PlacesKeyValidateAction;
use App\Services\Places\GooglePlacesClient;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout', ['heading' => 'Your Google Maps key'])]
final class PlacesKey extends Component
{
    public string $candidate = '';

    public ?string $hint = null;

    public string $inUse = 'platform';

    public function mount(CredentialHintAction $hintAction, GooglePlacesClient $places): void
    {
        abort_if(Tenancy::id() === null, 403);
        $biz = Tenancy::idOrFail();
        $this->hint = $hintAction->handle($biz, GooglePlacesClient::TENANT_SERVICE);
        $this->inUse = $places->keyInUse();
    }

    public function save(PlacesKeyValidateAction $validate, CredentialStoreAction $store, CredentialHintAction $hintAction, GooglePlacesClient $places): void
    {
        $biz = Tenancy::idOrFail();
        $this->validate(['candidate' => ['required', 'string', 'min:20', 'max:200']]);

        $r = $validate->handle($this->candidate);

        if (! $r['valid']) {
            Toaster::error('Google did not accept that key (HTTP '.$r['status'].'). Check that the Places API (New) is enabled and the key is not restricted to another site.');

            return;
        }

        $store->handle($biz, GooglePlacesClient::TENANT_SERVICE, $this->candidate);

        $this->candidate = '';
        $this->hint = $hintAction->handle($biz, GooglePlacesClient::TENANT_SERVICE);
        $this->inUse = $places->keyInUse();

        Toaster::success('Saved. Your own key is now used for map searches on this account.');
    }

    public function forget(CredentialForgetAction $forget, CredentialHintAction $hintAction, GooglePlacesClient $places): void
    {
        $biz = Tenancy::idOrFail();
        $forget->handle($biz, GooglePlacesClient::TENANT_SERVICE);

        $this->hint = $hintAction->handle($biz, GooglePlacesClient::TENANT_SERVICE);
        $this->inUse = $places->keyInUse();

        Toaster::success('Removed. The platform key is used again.');
    }

    public function render()
    {
        return view('livewire.account.places-key');
    }
}
