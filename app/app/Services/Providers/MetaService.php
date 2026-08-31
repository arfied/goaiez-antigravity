<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\ProviderRequestFailed;
use App\Models\Business;

/**
 * Meta Graph, through the vault.
 *
 * The version is pinned in config and must stay pinned: omitting it makes Meta
 * serve "the oldest available version"
 * (https://developers.facebook.com/docs/graph-api/overview, read 2026-07-31),
 * which is a silent downgrade rather than an error. v26.0 shipped 2026-07-29.
 *
 * Note that Socialite's own FacebookProvider still pins v23.0. Nothing here
 * goes through Socialite, so the two do not have to agree — but if the login
 * leg is ever made to share this version, `usingGraphVersion()` is the hook.
 *
 * PAGE TOKENS ARE THE POINT. A user access token lasts ~60 days and cannot be
 * renewed once lapsed. A long-lived *Page* token obtained from /me/accounts
 * with a long-lived user token has no expiry and "only expire[s] or [is]
 * invalidated under certain conditions". Anything that must keep working
 * unattended should be holding a page token, not a user token.
 */
final class MetaService extends ProviderClient
{
    protected function provider(): OauthProvider
    {
        return OauthProvider::Facebook;
    }

    /**
     * The Pages this connection administers, with their page access tokens.
     *
     * GET /{version}/me/accounts. Documented at
     * https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived
     * (read 2026-07-31) as the route to long-lived page tokens.
     *
     * The response contains credentials. It is returned to the caller so the
     * vault can store them, and it is never logged — VendorLog records status
     * and timing only.
     *
     * Failure modes: no connection (ProviderNotConnected, the caller falls back
     * to handoff), 4xx from an expired user token (permanent — the owner must
     * reconnect, which the vault's refresh path will have already prompted),
     * 5xx or unreachable (retryable).
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ProviderNotConnected
     * @throws ProviderRequestFailed
     */
    public function pages(Business $business): array
    {
        $url = sprintf(
            '%s/%s/me/accounts',
            rtrim((string) config('oauth.providers.facebook.graph_endpoint'), '/'),
            (string) config('oauth.providers.facebook.graph_version'),
        );

        $response = $this->send(
            $business,
            'GET',
            $url,
            // Bearer header rather than ?access_token=, which Meta also accepts.
            // A token in a query string reaches access logs, proxy logs and
            // Referer headers; a header does not.
            fn () => $this->authorized($business)->get($url, [
                'fields' => 'id,name,access_token,category',
            ]),
        );

        $pages = $response->json('data');

        return is_array($pages) ? $pages : [];
    }
}
