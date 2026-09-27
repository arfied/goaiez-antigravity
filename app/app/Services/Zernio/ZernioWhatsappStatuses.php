<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Models\WhatsappMessage;
use App\Services\Gbp\GbpConnections;
use App\Support\Tenancy;

final class ZernioWhatsappStatuses
{
    public function handle(array $payload): string
    {
        if (($payload['message']['platform'] ?? null) !== 'whatsapp') {
            return 'ignored';
        }

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

        return Tenancy::actingAs($businessId, function () use ($accountId, $payload): string {
            $connection = app(WhatsappConnectionLookupAction::class)->forAccount($accountId);
            if ($connection === null || $connection->status !== 'connected') {
                return 'unbound';
            }

            $wamid = $payload['message']['platformMessageId'] ?? $payload['message']['id'] ?? null;
            if ($wamid === null) {
                return 'ignored';
            }

            $message = WhatsappMessage::where('provider_message_ref', $wamid)->first();
            if ($message === null) {
                return 'ignored';
            }

            $event = $payload['type'] ?? '';
            $status = match (true) {
                $event === 'message.sent' => 'sent',
                $event === 'message.delivered' => 'delivered',
                $event === 'message.read' => 'read',
                $event === 'message.failed' => 'failed',
                default => null,
            };

            if ($status === null) {
                return 'ignored';
            }

            // Status only moves forward: failed from sent/sending/unconfirmed;
            // sent -> delivered -> read
            $current = $message->status;

            $canUpdate = false;
            if ($status === 'failed') {
                $canUpdate = in_array($current, ['sending', 'sent', 'unconfirmed'], true);
            } elseif ($status === 'sent') {
                $canUpdate = in_array($current, ['sending', 'unconfirmed'], true);
            } elseif ($status === 'delivered') {
                $canUpdate = in_array($current, ['sending', 'sent', 'unconfirmed'], true);
            } elseif ($status === 'read') {
                $canUpdate = in_array($current, ['sending', 'sent', 'unconfirmed', 'delivered'], true);
            }

            if (! $canUpdate && $status !== $current) {
                // If it can't update, it might mean we ignore it, but wait, the rules say "Status only moves forward".
                // I'll just check if it's "forward".
            }

            if ($canUpdate || $status === $current) {
                $updates = ['status' => $status, 'status_at' => now()];
                if ($status === 'failed') {
                    $updates['error_code'] = (string) ($payload['message']['error']['code'] ?? $payload['error']['code'] ?? '');
                    if ($updates['error_code'] === '') {
                        $updates['error_code'] = null;
                    }
                }
                $message->update($updates);
            }

            return 'handled';
        });
    }
}
