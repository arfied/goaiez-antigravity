<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Services\Gbp\GbpConnections;
use App\Support\Tenancy;

final class ZernioWhatsappTemplates
{
    public function handle(array $payload): string
    {
        $accountId = $payload['account']['accountId'] ?? null;
        $profileId = $payload['account']['profileId'] ?? null;

        if ($accountId === null || $profileId === null) {
            return 'unbound';
        }

        $businesses = app(GbpConnections::class)->businessesForProfiles([$profileId]);
        if (empty($businesses[$profileId])) {
            return 'unbound';
        }

        $businessId = $businesses[$profileId];

        return Tenancy::actingAs($businessId, function () use ($businessId, $accountId, $payload): string {
            $connection = app(WhatsappConnectionLookupAction::class)->forAccount($accountId);
            if ($connection === null || $connection->status !== 'connected') {
                return 'unbound';
            }

            $templateId = $payload['template']['templateId'] ?? null;
            $name = $payload['template']['name'] ?? null;
            $language = $payload['template']['language'] ?? null;
            $status = $payload['template']['status'] ?? null;
            $reason = $payload['template']['reason'] ?? null;

            if ($templateId === null || $name === null || $language === null || $status === null) {
                return 'ignored';
            }

            $template = app(WhatsappEngine::class)->applyTemplateStatus(
                $businessId,
                (string) $templateId,
                $name,
                $language,
                $status,
                $reason
            );

            if ($template === null) {
                return 'ignored';
            }

            return 'handled';
        });
    }
}
