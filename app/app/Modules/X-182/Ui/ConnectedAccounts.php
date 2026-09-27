<?php

declare(strict_types=1);

namespace App\Modules\X182\Ui;

use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Exceptions\ZernioConnectionRefused;
use App\Http\Controllers\Social\SocialConnectController;
use App\Models\Location;
use App\Modules\X182\Domain\SocialConnections;
use App\Modules\X182\Models\SocialAccount;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout', ['heading' => 'Connected accounts'])]
class ConnectedAccounts extends Component
{
    #[Locked]
    public int $businessId = 0;

    public ?int $locationId = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId ?: Tenancy::id() ?? 0;
        abort_if($this->businessId === 0, 404);
    }

    public function connect(string $platform, SocialConnections $action, ?int $locationId = null): void
    {
        abort_unless(auth()->user()?->role->canManageConnections() === true, 403);

        $resolvedLocationId = $locationId ?? $this->locationId;

        if ($resolvedLocationId === null && $platform === 'facebook') {
            $locations = Location::where('business_id', $this->businessId)->get();
            if ($locations->count() === 1) {
                $resolvedLocationId = $locations->first()->id;
            }
        }

        try {
            $url = $action->begin(
                $platform,
                $resolvedLocationId ? (int) $resolvedLocationId : null,
                fn (string $profile): string => SocialConnectController::callbackUrlFor($platform, $profile),
                'user:'.(int) auth()->id()
            );
        } catch (GbpRequestFailed) {
            Toaster::error('We could not start that connection. Please try again shortly.');

            return;
        } catch (ImpersonationRefused $refused) {
            Toaster::warning($refused->getMessage());

            return;
        } catch (ZernioConnectionRefused $refused) {
            $refused->reason === 'ceiling_reached'
                ? Toaster::info($refused->getMessage())
                : Toaster::error($refused->getMessage());

            return;
        }

        $this->redirect($url);
    }

    public function disconnect(int $id, SocialConnections $action): void
    {
        abort_unless(auth()->user()?->role->canManageConnections() === true, 403);

        $account = SocialAccount::where('id', $id)->where('business_id', $this->businessId)->firstOrFail();
        $action->disconnect($account, 'user:'.(int) auth()->id());
    }

    public function render()
    {
        $accounts = ($this->businessId > 0)
            ? SocialAccount::where('business_id', $this->businessId)->get()
            : collect();

        $locations = ($this->businessId > 0)
            ? Location::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-182::connected-accounts', [
            'accounts' => $accounts,
            'locations' => $locations,
        ]);
    }
}
