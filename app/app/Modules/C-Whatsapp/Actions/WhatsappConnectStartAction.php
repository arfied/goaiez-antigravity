<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Actions;

use App\Enums\ImpersonationCapability;
use App\Exceptions\ZernioConnectionRefused;
use App\Http\Controllers\Whatsapp\WhatsappConnectController;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Services\Gbp\GbpConnections;
use App\Services\Gbp\ZernioSpend;
use App\Services\Impersonation\Impersonation;
use App\Services\Zernio\ZernioWhatsappClient;

final class WhatsappConnectStartAction
{
    public function __construct(
        private readonly Impersonation $impersonation,
        private readonly ZernioSpend $spend,
        private readonly GbpConnections $gbpConnections,
        private readonly ZernioWhatsappClient $whatsappClient,
    ) {}

    public function handle(string $actor): string
    {
        $this->impersonation->refuse(ImpersonationCapability::ManageConnections);

        $hasConnected = WhatsappConnection::where('status', 'connected')->exists();

        if (! $hasConnected && ! $this->spend->allowsNewAccount()) {
            throw ZernioConnectionRefused::ceilingReached('WhatsApp');
        }

        $profile = $this->gbpConnections->zernioProfileForCurrentBusiness();

        WhatsappConnection::updateOrCreate(
            [],
            [
                'status' => 'pending',
                'provider_profile_ref' => $profile,
                'last_error' => null,
            ]
        );

        return $this->whatsappClient->connectUrl($profile, WhatsappConnectController::callbackUrlFor($profile));
    }
}
