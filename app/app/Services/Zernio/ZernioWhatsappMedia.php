<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;

final class ZernioWhatsappMedia
{
    public const int MAX_BYTES = 16777216;

    public function __construct(
        private readonly ZernioHttp $http,
        private readonly DefaultsRegistry $defaults
    ) {}

    /**
     * @return array{bytes: string, mime: ?string}
     */
    public function download(string $accountRef, string $mediaId): array
    {
        $this->http->assertUsable('whatsapp.zernio_enabled');

        $response = $this->http->get('whatsapp/media/'.rawurlencode($mediaId), ['accountId' => $accountRef]);

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $bytes = $response->body();

        if (strlen($bytes) > $this->defaults->int('whatsapp.media_max_bytes')) {
            throw GbpRequestFailed::unreadable('media_too_large');
        }

        return ['bytes' => $bytes, 'mime' => $response->header('Content-Type') ?: null];
    }
}
