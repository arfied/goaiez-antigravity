<?php

declare(strict_types=1);

namespace App\Services\Zernio;

use App\Exceptions\GbpRequestFailed;
use InvalidArgumentException;

final class ZernioAccounts
{
    private const array FLAG_FOR = [
        'facebook' => 'social.zernio_enabled',
        'instagram' => 'social.zernio_enabled',
    ];

    public function __construct(
        private readonly ZernioHttp $http,
    ) {}

    public function connectUrl(string $platform, string $profileRef, string $redirectUrl, array $extra = []): string
    {
        if (! isset(self::FLAG_FOR[$platform])) {
            throw new InvalidArgumentException("Platform {$platform} is not supported.");
        }

        $this->http->assertUsable(self::FLAG_FOR[$platform]);

        $query = array_merge([
            'profileId' => $profileRef,
            'redirect_url' => $redirectUrl,
        ], $extra);

        $response = $this->http->get("/connect/{$platform}", $query);

        if (! $response->successful()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $authUrl = $response->json('authUrl');

        if (! is_string($authUrl)) {
            throw GbpRequestFailed::unreadable('auth_url_missing');
        }

        return $authUrl;
    }

    public function accountsOnProfile(string $platform, string $profileRef): array
    {
        if (! isset(self::FLAG_FOR[$platform])) {
            throw new InvalidArgumentException("Platform {$platform} is not supported.");
        }

        $this->http->assertUsable(self::FLAG_FOR[$platform]);

        $response = $this->http->get('/accounts', [
            'profileId' => $profileRef,
            'platform' => $platform,
            'includeOverLimit' => 'true',
        ]);

        if (! $response->successful()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $accounts = $response->json('accounts');

        if (! is_array($accounts)) {
            throw GbpRequestFailed::unreadable('accounts_missing');
        }

        $ids = [];
        foreach ($accounts as $account) {
            if (isset($account['_id']) && is_string($account['_id'])) {
                $ids[] = $account['_id'];
            }
        }

        return $ids;
    }

    public function profileOwnsAccount(string $platform, string $profileRef, string $accountRef): bool
    {
        return in_array($accountRef, $this->accountsOnProfile($platform, $profileRef), true);
    }

    public function disconnectAccount(string $platform, string $accountRef): void
    {
        if (! isset(self::FLAG_FOR[$platform])) {
            throw new InvalidArgumentException("Platform {$platform} is not supported.");
        }

        $this->http->assertUsable(self::FLAG_FOR[$platform]);

        $response = $this->http->delete("/accounts/{$accountRef}");

        if (! $response->successful() && $response->status() !== 404) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }
    }
}
