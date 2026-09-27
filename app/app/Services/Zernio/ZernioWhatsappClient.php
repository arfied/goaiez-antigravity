<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class ZernioWhatsappClient
{
    private const string BASE = 'https://zernio.com/api/v1';

    private const string PLATFORM = 'whatsapp';

    public function __construct(
        private readonly DefaultsRegistry $registry,
    ) {}

    private function request(): PendingRequest
    {
        return Http::withToken(PlatformCredentials::get('zernio_api_key'))
            ->timeout((int) config('gbp.timeout', 10))
            ->acceptJson();
    }

    private function assertUsable(): void
    {
        if ($this->registry->value('whatsapp.zernio_enabled') !== true) {
            throw GbpRequestFailed::disabled();
        }

        if (! PlatformCredentials::has('zernio_api_key')) {
            throw GbpRequestFailed::unconfigured();
        }
    }

    public function connectUrl(string $profileRef, string $redirectUrl): string
    {
        $this->assertUsable();

        try {
            $response = $this->request()->get(self::BASE.'/connect/whatsapp', [
                'profileId' => $profileRef,
                'redirect_url' => $redirectUrl,
                'onboarding' => (string) $this->registry->value('whatsapp.zernio_onboarding'),
            ]);
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $authUrl = $response->json('authUrl');

        if (! is_string($authUrl) || $authUrl === '') {
            throw GbpRequestFailed::unreadable('auth_url_missing');
        }

        return $authUrl;
    }

    public function profileOwnsAccount(string $profileRef, string $accountRef): bool
    {
        $this->assertUsable();

        try {
            $response = $this->request()->get(self::BASE.'/accounts', [
                'profileId' => $profileRef,
                'platform' => self::PLATFORM,
                'includeOverLimit' => 'true',
            ]);
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $accounts = $response->json('accounts');

        if (! is_array($accounts)) {
            throw GbpRequestFailed::unreadable('accounts_missing');
        }

        foreach ($accounts as $account) {
            if (is_array($account) && ($account['_id'] ?? null) === $accountRef) {
                return true;
            }
        }

        return false;
    }

    public function disconnectAccount(string $accountRef): void
    {
        $this->assertUsable();

        try {
            $response = $this->request()->delete(self::BASE.'/accounts/'.rawurlencode($accountRef));
        } catch (ConnectionException) {
            throw GbpRequestFailed::unreachable('connection_failed');
        }

        if ($response->failed() && $response->status() !== 404) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }
    }
}
