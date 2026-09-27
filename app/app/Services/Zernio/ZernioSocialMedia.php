<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Services\Fetch\PublicAddressGuard;

final class ZernioSocialMedia
{
    public const int MAX_BYTES = 16777216;

    public function __construct(
        private readonly ZernioHttp $http,
        private readonly DefaultsRegistry $defaults,
    ) {}

    public function download(string $accountRef, string $conversationRef, string $platformMessageId, int $index): array
    {
        $this->http->assertUsable('social.zernio_enabled');

        $r = $this->http->get('inbox/conversations/'.rawurlencode($conversationRef).'/messages/'.rawurlencode($platformMessageId).'/attachments/'.$index, [
            'accountId' => $accountRef,
            'format' => 'json',
        ]);

        if (! $r->successful()) {
            throw GbpRequestFailed::from($r, accountScoped: true);
        }

        $url = $r->json('url');
        if (! is_string($url) || $url === '') {
            throw GbpRequestFailed::unreadable('media_url_missing');
        }

        $ips = app(PublicAddressGuard::class)->check($url);
        if ($ips === null || $ips === []) {
            throw GbpRequestFailed::unreadable('media_url_refused');
        }

        $response = $this->http->fetchMediaUrl($url, $ips);

        if (! $response->successful()) {
            throw GbpRequestFailed::unreadable('media_fetch_status');
        }

        $body = $response->body();
        if (strlen($body) > $this->defaults->int('social.dm_media_max_bytes')) {
            throw GbpRequestFailed::unreadable('media_too_large');
        }

        return [
            'bytes' => $body,
            'mime' => $response->header('Content-Type') ?: null,
        ];
    }
}
