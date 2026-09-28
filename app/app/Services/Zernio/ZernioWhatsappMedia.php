<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Jobs\Sms\CaptureInboundMediaJob;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ZernioWhatsappMedia
{
    public const int MAX_BYTES = 16777216;

    public const string DISK = CaptureInboundMediaJob::DISK;

    public const string PREFIX = 'whatsapp-media';

    public function __construct(
        private readonly ZernioHttp $http,
        private readonly DefaultsRegistry $defaults
    ) {}

    public function pathFor(int $businessId, int $messageId, int $index): string
    {
        return self::PREFIX.'/'.$businessId.'/'.$messageId.'/'.$index;
    }

    public function purgeAllFor(int $businessId): bool
    {
        try {
            $disk = Storage::disk(self::DISK);
            $disk->deleteDirectory(self::PREFIX.'/'.$businessId);

            return $disk->allFiles(self::PREFIX.'/'.$businessId) === [];
        } catch (Throwable) {
            // ⚠️ Refuses rather than throws, decision 823's rule: this is
            // reached from a sweep that walks every due deletion, and one
            // unreachable store must not abandon the rest of the queue.
            return false;
        }
    }

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
