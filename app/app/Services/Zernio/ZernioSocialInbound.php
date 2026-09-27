<?php

declare(strict_types=1);

namespace App\Services\Zernio;

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

        return Tenancy::actingAs($businessId, function () use ($platform, $conversationRef, $accountRef, $message): string {
            $sender = $message['sender'] ?? [];
            $label = $sender['name'] ?? $sender['username'] ?? null;

            $thread = app(ConversationThreads::class)->socialThread($platform, $conversationRef, $accountRef, $label);

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

            app(ConversationThreads::class)->recordInbound($thread, $body);

            return 'handled';
        });
    }
}
