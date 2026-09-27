<?php

declare(strict_types=1);

namespace App\Modules\X207\Domain;

use App\Support\PlatformCredentials;
use GuzzleHttp\Client;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;

final class WebPushTransport
{
    public function __construct(private readonly ?ClientInterface $client = null) {}

    public function isConfigured(): bool
    {
        return PlatformCredentials::has('webpush_vapid_public_key') && PlatformCredentials::has('webpush_vapid_private_key');
    }

    public function publicKey(): ?string
    {
        return PlatformCredentials::has('webpush_vapid_public_key') ? PlatformCredentials::get('webpush_vapid_public_key') : null;
    }

    public function send(array $subscription, array $payload): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'expired' => false, 'reason' => 'not_configured'];
        }

        $vapid = [
            'subject' => config('app.url'),
            'publicKey' => PlatformCredentials::get('webpush_vapid_public_key'),
            'privateKey' => PlatformCredentials::get('webpush_vapid_private_key'),
        ];

        $client = $this->client ?? new Client(['timeout' => 10]);
        $webPush = new WebPush(['VAPID' => $vapid], [], $client);

        $sub = Subscription::create($subscription);
        $report = $webPush->sendOneNotification($sub, json_encode($payload));

        return [
            'ok' => $report->isSuccess(),
            'expired' => $report->isSubscriptionExpired(),
            'reason' => $report->getReason(),
        ];
    }
}
