<?php

declare(strict_types=1);

namespace App\Modules\X182\Domain;

use App\Enums\ImpersonationCapability;
use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ZernioConnectionRefused;
use App\Models\Location;
use App\Models\ZernioAccountBinding;
use App\Modules\X182\Models\SocialAccount;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\ZernioSpend;
use App\Services\Impersonation\Impersonation;
use App\Services\Zernio\ZernioAccounts;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SocialConnections
{
    public function __construct(
        private readonly Impersonation $impersonation,
        private readonly ZernioSpend $spend,
        private readonly GbpConnections $gbpConnections,
        private readonly ZernioAccounts $zernioAccounts,
    ) {}

    public function begin(string $platform, ?int $locationId, string $redirectUrl, string $actor): string
    {
        $this->impersonation->refuse(ImpersonationCapability::ManageConnections);

        $hasConnected = SocialAccount::where('business_id', Tenancy::idOrFail())
            ->where('platform', $platform)
            ->where('status', 'connected')
            ->exists();

        if (! $hasConnected && ! $this->spend->allowsNewAccount()) {
            throw ZernioConnectionRefused::ceilingReached(ucfirst($platform));
        }

        if ($platform === 'facebook') {
            if ($locationId === null || ! Location::where('id', $locationId)->exists()) {
                throw new InvalidArgumentException('Location ID must belong to this tenant for Facebook.');
            }
        }

        $profile = $this->gbpConnections->zernioProfileForCurrentBusiness();

        $row = SocialAccount::where('business_id', Tenancy::idOrFail())
            ->where('platform', $platform)
            ->first();

        if ($row === null) {
            $row = new SocialAccount;
            $row->business_id = Tenancy::idOrFail();
            $row->platform = $platform;
        }

        $row->status = 'pending';
        $row->provider_profile_ref = $profile;
        $row->location_id = $locationId;
        $row->last_error = null;
        $row->save();

        return $this->zernioAccounts->connectUrl($platform, $profile, $redirectUrl);
    }

    public function complete(string $platform, ?string $accountRef, string $expectedProfileRef, ?string $profileRef, ?string $label, string $actor): SocialAccount
    {
        $row = SocialAccount::where('business_id', Tenancy::idOrFail())
            ->where('platform', $platform)
            ->first();

        if ($row === null || $row->status !== 'pending') {
            throw ZernioConnectionRefused::notStarted();
        }

        if ($expectedProfileRef !== $row->provider_profile_ref || ($profileRef !== null && $profileRef !== $expectedProfileRef)) {
            throw ZernioConnectionRefused::wrongProfile();
        }

        if ($accountRef === null) {
            $accounts = $this->zernioAccounts->accountsOnProfile($platform, $expectedProfileRef);
            if (count($accounts) !== 1) {
                throw ZernioConnectionRefused::accountNotOnProfile(ucfirst($platform));
            }
            $accountRef = $accounts[0];
        } else {
            if (! $this->zernioAccounts->profileOwnsAccount($platform, $expectedProfileRef, $accountRef)) {
                throw ZernioConnectionRefused::accountNotOnProfile(ucfirst($platform));
            }
        }

        return DB::transaction(function () use ($row, $accountRef, $expectedProfileRef, $label, $platform): SocialAccount {
            $existingBinding = ZernioAccountBinding::where('account_ref', $accountRef)->first();

            if ($existingBinding !== null && $existingBinding->profile_ref !== $expectedProfileRef) {
                throw ZernioConnectionRefused::wrongProfile();
            }

            $row->account_ref = $accountRef;
            if ($label !== null) {
                $row->account_handle = $label;
            }
            $row->status = 'connected';
            $row->is_connected = true;
            $row->connected_at = now();
            $row->last_error = null;
            $row->save();

            ZernioAccountBinding::firstOrCreate(
                ['account_ref' => $accountRef],
                ['profile_ref' => $expectedProfileRef, 'platform' => $platform]
            );

            return $row;
        });
    }

    public function disconnect(SocialAccount $row, string $actor): void
    {
        try {
            $this->zernioAccounts->disconnectAccount($row->platform, $row->account_ref);

            ZernioAccountBinding::where('account_ref', $row->account_ref)->delete();

            $row->status = 'disconnected';
            $row->is_connected = false;
            $row->disconnected_at = now();
            $row->save();
        } catch (GbpRequestFailed) {
            ZernioAccountBinding::where('account_ref', $row->account_ref)->update(['revocation_owed_at' => now()]);

            $row->status = 'disconnected';
            $row->last_error = 'Zernio did not confirm the disconnect.';
            $row->save();
        }
    }

    public function recordExternalDisconnect(SocialAccount $row): void
    {
        $row->status = 'disconnected';
        $row->is_connected = false;
        $row->last_error = 'Disconnected at Zernio.';
        $row->save();

        ZernioAccountBinding::where('account_ref', $row->account_ref)->delete();
    }
}
