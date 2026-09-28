<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Enums\DataClassification;
use App\Models\Business;
use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Services\Gbp\GbpConnections;
use App\Support\Tenancy;

final class ZernioWhatsappInbound
{
    public function handle(array $payload): string
    {
        if (($payload['message']['platform'] ?? null) !== 'whatsapp') {
            return 'ignored';
        }

        if (($payload['message']['direction'] ?? null) !== 'incoming') {
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

        return Tenancy::actingAs($businessId, function () use ($businessId, $accountId, $payload): string {
            $connection = app(WhatsappConnectionLookupAction::class)->forAccount($accountId);
            if ($connection === null || $connection->status !== 'connected') {
                return 'unbound';
            }

            $rawPhone = $payload['message']['sender']['phoneNumber'] ?? null;
            if ($rawPhone === null) {
                $phone = '';
            } else {
                $phone = '+'.preg_replace('/[^0-9]/', '', (string) $rawPhone);
            }

            $body = $payload['message']['text'] ?? '';
            if ($body === '' && ! empty($payload['message']['attachments'])) {
                $firstType = $payload['message']['attachments'][0]['type'] ?? 'unknown';
                $body = "[attachment: {$firstType}]";
            }

            $attachments = [];
            $rawAttachments = array_slice($payload['message']['attachments'] ?? [], 0, 4);

            $business = Business::query()->find($businessId);
            $healthTenant = $business !== null && $business->data_classification === DataClassification::Phi;

            foreach ($rawAttachments as $a) {
                $id = $a['payload']['id'] ?? null;
                $type = (string) ($a['type'] ?? 'file');

                if ($healthTenant) {
                    $attachments[] = [
                        'type' => $type,
                        'status' => 'refused_health_tenant',
                    ];
                } elseif (is_string($id) && $id !== '') {
                    $attachments[] = [
                        'type' => $type,
                        'mime' => $a['mimeType'] ?? null,
                        'media_id' => $id,
                        'account_ref' => $accountId,
                        'status' => 'pending',
                    ];
                } else {
                    $attachments[] = [
                        'type' => $type,
                        'status' => 'unavailable',
                    ];
                }
            }

            $senderName = (string) ($payload['message']['sender']['name'] ?? '');

            $conversationId = (string) ($payload['conversation']['id'] ?? ($payload['message']['conversationId'] ?? ''));

            $bsuid = $payload['message']['sender']['businessScopedUserId'] ?? null;
            $wamid = $payload['message']['platformMessageId'] ?? null;

            app(WhatsappEngine::class)->recordInbound(
                $businessId,
                $phone,
                $body,
                $senderName,
                $conversationId === '' ? null : $conversationId,
                $bsuid,
                $wamid,
                $attachments
            );

            if ($phone === '') {
                return 'unidentified';
            }

            return 'handled';
        });
    }
}
