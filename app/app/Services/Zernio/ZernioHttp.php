<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class ZernioHttp
{
    public const string BASE = 'https://zernio.com/api/v1';

    public function assertUsable(string $flagKey): void
    {
        if (app(DefaultsRegistry::class)->value($flagKey) !== true) {
            throw GbpRequestFailed::disabled();
        }

        if (! PlatformCredentials::has('zernio_api_key')) {
            throw GbpRequestFailed::unconfigured();
        }
    }

    public function post(string $path, array $body, ?string $idempotencyKey = null): Response
    {
        $url = self::BASE.'/'.ltrim($path, '/');

        $request = Http::withToken(PlatformCredentials::get('zernio_api_key'))
            ->timeout((int) config('gbp.timeout', 10))
            ->acceptJson();

        if ($idempotencyKey !== null) {
            $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            return VendorLog::timed('zernio', 'POST', $url, fn () => $request->post($url, $body));
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }
    }
}
