<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Enums\DataClassification;
use App\Models\Business;
use App\Models\ZernioAccountBinding;
use App\Services\Conversations\ConversationThreads;
use App\Services\Gbp\GbpConnections;
use App\Support\Tenancy;

final class ZernioSocialInbound
{
    public function __construct(private readonly GbpConnections $connections) {}

    public function handle(array $payload): string
    {
        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return 'ignored';
        }

        $platform = $message['platform'] ?? null;
        if (! in_array($platform, ['facebook', 'instagram'], true)) {
            return 'ignored';
        }

        if (($message['direction'] ?? null) !== 'incoming') {
            return 'ignored';
        }

        $conversationRef = $message['conversationId'] ?? null;
        if (! is_string($conversationRef) || $conversationRef === '') {
            return 'ignored';
        }

        $accountRef = $payload['account']['accountId'] ?? null;
        if (! is_string($accountRef) || $accountRef === '') {
            return 'ignored';
        }

        $binding = ZernioAccountBinding::where('account_ref', $accountRef)->first();
        if ($binding === null || ! in_array($binding->platform, ['facebook', 'instagram'], true)) {
            return 'unbound';
        }

        $businesses = $this->connections->businessesForProfiles([$binding->profile_ref]);
        $businessId = $businesses[$binding->profile_ref] ?? null;

        if ($businessId === null) {
            return 'unbound';
        }

        return Tenancy::actingAs($businessId, function () use ($businessId, $platform, $conversationRef, $accountRef, $message): string {
            $text = $message['text'] ?? null;
            $attachments = $message['attachments'] ?? [];

            if (is_string($text) && $text !== '') {
                $body = $text;
            } elseif (is_array($attachments) && count($attachments) > 0) {
                $type = $attachments[0]['type'] ?? 'unknown';
                $body = "[attachment: {$type}]";
            } else {
                return 'ignored';
            }

            $business = Business::query()->find($businessId);
            $healthTenant = $business !== null && $business->data_classification === DataClassification::Phi;

            $saved = [];
            $rawAttachments = array_slice($attachments, 0, 4);

            foreach ($rawAttachments as $i => $a) {
                $type = (string) ($a['type'] ?? 'file');

                if ($healthTenant) {
                    $saved[] = [
                        'type' => $type,
                        'status' => 'refused_health_tenant',
                    ];
                } elseif (is_string($message['platformMessageId'] ?? null) && $message['platformMessageId'] !== '') {
                    $saved[] = [
                        'type' => $type,
                        'mime' => $a['mimeType'] ?? null,
                        'source' => 'meta',
                        'conversation_ref' => $conversationRef,
                        'platform_message_id' => $message['platformMessageId'],
                        'index' => $i,
                        'account_ref' => $accountRef,
                        'status' => 'pending',
                    ];
                } else {
                    $saved[] = [
                        'type' => $type,
                        'status' => 'unavailable',
                    ];
                }
            }

            $sender = $message['sender'] ?? [];
            $label = $sender['name'] ?? $sender['username'] ?? null;
            $label = is_string($label) ? $label : null;

            $thread = app(ConversationThreads::class)->socialThread($platform, $conversationRef, $accountRef, $label);
            app(ConversationThreads::class)->recordInbound($thread, $body, $saved);

            return 'handled';
        });
    }
}
