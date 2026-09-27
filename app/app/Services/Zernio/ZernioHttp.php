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

    public function get(string $path, array $query = []): Response
    {
        $url = self::BASE.'/'.ltrim($path, '/');

        $request = Http::withToken(PlatformCredentials::get('zernio_api_key'))
            ->timeout((int) config('gbp.timeout', 10))
            ->acceptJson();

        try {
            return VendorLog::timed('zernio', 'GET', $url, fn () => $request->get($url, $query));
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }
    }

    public function delete(string $path): Response
    {
        $url = self::BASE.'/'.ltrim($path, '/');

        $request = Http::withToken(PlatformCredentials::get('zernio_api_key'))
            ->timeout((int) config('gbp.timeout', 10))
            ->acceptJson();

        try {
            return VendorLog::timed('zernio', 'DELETE', $url, fn () => $request->delete($url));
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }
    }

    /**
     * This is the ONLY method here that reaches a non-Zernio host.
     * It exists because getMessageAttachment answers with a short-lived Meta CDN link that must be fetched at once.
     * It sends NO Authorization header.
     */
    public function fetchMediaUrl(string $url, array $ips): Response
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw GbpRequestFailed::unreadable('media_url_refused');
        }

        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?: 443;

        try {
            return VendorLog::timed('zernio', 'GET', $url, fn () => Http::timeout((int) config('gbp.timeout', 10))->withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ips[0]}"]],
            ])->get($url));
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('media_fetch_failed');
        }
    }
}
