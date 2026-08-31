<?php

declare(strict_types=1);

namespace App\Services\Oauth;

use App\Enums\AutopilotActionType;
use App\Enums\ConnectionStatus;
use App\Enums\OauthProvider;
use App\Exceptions\ProviderNotConnected;
use App\Exceptions\TenantMismatch;
use App\Exceptions\TokenRefreshFailed;
use App\Models\Business;
use App\Models\Notification;
use App\Models\OauthConnection;
use App\Models\ProviderHealth;
use App\Services\ActivityService;
use App\Support\Tenancy;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * The multi-provider token vault (FOUND-03).
 *
 * One encrypted store for Google, Meta and Microsoft, with refresh that happens
 * without anyone asking for it — and, when refresh cannot save the connection, a
 * Reconnect prompt that says so in the owner's language.
 *
 * ENCRYPTION IS EXPLICIT, NOT A CAST. The obvious implementation is an
 * `encrypted` cast on the two token columns, and it is the wrong one here.
 * A cast decrypts on every attribute read, so `$connection->access_token_enc`
 * hands plaintext to anything that touches the model — a `dd()`, a log of
 * `getAttributes()`, an exception's context, a Livewire property. Encrypting at
 * this boundary means the model literally never holds plaintext: the column
 * reads back as ciphertext everywhere in the application except the three lines
 * below that deliberately decrypt it. `$hidden` on the model is the second
 * layer, not the first.
 *
 * TENANCY. Every method takes the business for legibility and takes the actual
 * tenant from context, asserting the two agree — see TenantMismatch for why the
 * argument must not be trusted as the scope. Callers establish the tenant first;
 * jobs do it in handle(), the web path does it in ResolveTenant.
 *
 * @see docs/17-TIER1-EXECUTABLE-TICKETS.md FOUND-03
 */
final class TokenService
{
    /**
     * @param  array<string, TokenRefresher>  $refreshers  Keyed by OauthProvider
     *                                                     value. A provider
     *                                                     absent from this map
     *                                                     has no refresh path,
     *                                                     which is a state the
     *                                                     vault reports rather
     *                                                     than guesses around.
     */
    public function __construct(
        private readonly array $refreshers,
        private readonly ActivityService $activity,
    ) {}

    /**
     * A usable access token, refreshing first if one is due.
     *
     * "Due" is expiry minus the configured window, not expiry — a token that
     * expires during the call it was fetched for is no better than one that
     * expired before it.
     *
     * @throws ProviderNotConnected when there is nothing usable and no way to
     *                              get there without the owner
     * @throws TokenRefreshFailed when the provider was unreachable. Transient;
     *                            the caller's job retries with backoff.
     */
    public function getValidToken(Business $business, OauthProvider $provider): string
    {
        $connection = $this->connection($business, $provider);

        if ($connection === null) {
            throw ProviderNotConnected::missing($provider);
        }

        if ($connection->status !== ConnectionStatus::Active) {
            throw ProviderNotConnected::unusable($provider);
        }

        if ($this->isDue($connection)) {
            $connection = $this->refresh($connection);
        }

        $token = $this->decrypt($connection->access_token_enc);

        if ($token === null) {
            throw ProviderNotConnected::unusable($provider);
        }

        return $token;
    }

    /**
     * Persist a newly authorized connection.
     *
     * Called from the OAuth callback with what the provider just handed over.
     * `updateOrCreate` on (business, provider, external account) because
     * reconnecting an existing account must replace its credentials rather than
     * accumulate rows — and the match clause carries the tenant explicitly, so
     * it can never find and mutate another business's connection even if the
     * global scope were ever removed.
     *
     * @param  list<string>|null  $scopes
     */
    public function store(
        Business $business,
        OauthProvider $provider,
        string $accessToken,
        ?string $refreshToken = null,
        ?Carbon $expiresAt = null,
        ?string $externalAccountId = null,
        ?string $displayLabel = null,
        ?array $scopes = null,
    ): OauthConnection {
        $tenantId = $this->tenantFor($business);

        $connection = OauthConnection::updateOrCreate(
            [
                'business_id' => $tenantId,
                'provider' => $provider,
                'external_account_id' => $externalAccountId,
            ],
            [
                'display_label' => $displayLabel,
                'access_token_enc' => Crypt::encryptString($accessToken),
                'refresh_token_enc' => $refreshToken === null
                    ? null
                    : Crypt::encryptString($refreshToken),
                'token_expires_at' => $expiresAt,
                'scopes' => $scopes,
                'status' => ConnectionStatus::Active,
                'last_refreshed_at' => Carbon::now(),
                'last_error' => null,
            ],
        );

        $this->recordHealth($provider, 'active');

        // Reconnecting clears the prompt that asked for it. Leaving it would
        // have the owner staring at a warning about something they just fixed.
        $this->clearReconnectPrompts($provider);

        return $connection;
    }

    /**
     * Refresh one connection, whatever that means for its provider.
     *
     * Returns the refreshed model. On permanent failure the connection is
     * flipped to expired, provider_health is written, and the owner is asked to
     * reconnect — then ProviderNotConnected is thrown, because from the caller's
     * point of view there is still no token.
     *
     * @throws ProviderNotConnected
     * @throws TokenRefreshFailed
     */
    public function refresh(OauthConnection $connection): OauthConnection
    {
        $provider = $connection->provider;

        $refresher = $this->refreshers[$provider->value] ?? null;

        if ($refresher === null) {
            // Not a failure of this connection — a provider we never taught the
            // vault to refresh. Say so plainly rather than marking the owner's
            // perfectly good connection as broken.
            $this->markUnusable($connection, 'unsupported_provider');

            throw ProviderNotConnected::unusable($provider);
        }

        try {
            $credentials = $refresher->refresh(new StoredCredentials(
                $this->decrypt($connection->access_token_enc),
                $this->decrypt($connection->refresh_token_enc),
            ));
        } catch (TokenRefreshFailed $e) {
            if (! $e->permanent) {
                // ⛔ **THE PROVIDER HAD A BAD MOMENT — OR WE DID, AND THOSE ARE
                // NOT THE SAME ROW** (7400). This arm recorded every transient
                // failure alike, so a `ConnectionException` in any of the three
                // refreshers reached here as `'connection'` and was written as
                // `last_error` **on the tenant's health row**. That is 7261's
                // defect two frames further out, which is exactly why the catch
                // -body lint written for 7261 could not see it: nothing in the
                // catch mentions health, and by the time anything does, the
                // frame that knew has returned.
                //
                // ⚠️ **THE PROVIDER'S OWN 5xx IS STILL RECORDED, AND MUST BE.**
                // `provider_health` is a table of sentences about the far end,
                // and *"Google answered 503"* is one. *"We could not resolve
                // Google"* is not — it is a sentence about us, filed under
                // somebody else's grant.
                //
                // ⚠️ **AND THE STATUS IS LEFT WHERE THE LAST REAL ANSWER PUT
                // IT.** This wrote `'active'`, which reads as an all-clear: a
                // grant sitting at `error` from a genuine 403 was quietly
                // marked healthy by an unrelated Google outage. A vendor's own
                // failure is not evidence about the grant in either direction,
                // so it changes `last_error` and leaves the light alone.
                if ($e->reachedVendor) {
                    $this->recordHealth($provider, null, $e->reason);
                }

                throw $e;
            }

            $this->markUnusable($connection, $e->reason);

            throw ProviderNotConnected::unusable($provider);
        }

        $connection->forceFill([
            'access_token_enc' => Crypt::encryptString($credentials->accessToken),
            // Null means "the provider returned none", which for Google is the
            // normal case and must not clear the stored token. Microsoft rotates
            // on every refresh and the new one replaces the old.
            'refresh_token_enc' => $credentials->refreshToken === null
                ? $connection->refresh_token_enc
                : Crypt::encryptString($credentials->refreshToken),
            'token_expires_at' => $credentials->expiresAt,
            'scopes' => $credentials->scopes ?? $connection->scopes,
            'status' => ConnectionStatus::Active,
            'last_refreshed_at' => Carbon::now(),
            'last_error' => null,
        ])->save();

        $this->recordHealth($provider, 'active');

        return $connection;
    }

    /**
     * Whether a connection's token is expired or close enough to count.
     *
     * A null expiry means NOT due, and the reason is Meta. Long-lived Page
     * tokens genuinely have no expiration — they "only expire or are invalidated
     * under certain conditions" — so null is a real state rather than missing
     * data.
     *
     * The alternative reading, "unknown age, refresh to be safe", is worse in
     * exactly the case that matters. Refreshing a Page token means calling
     * fb_exchange_token with a credential that grant does not apply to; a
     * permanent failure there runs markUnusable(), which clears the stored
     * tokens. That trades a working, non-expiring credential for a Reconnect
     * prompt the owner did not need.
     *
     * Nothing is lost by the safer reading: all three providers return
     * `expires_in` on every grant, so a token that does expire arrives with its
     * expiry attached. One that somehow does not simply fails at the API, where
     * the failure is classified and recorded rather than acted on destructively.
     */
    public function isDue(OauthConnection $connection): bool
    {
        $expiresAt = $connection->token_expires_at;

        if ($expiresAt === null) {
            return false;
        }

        $window = (int) config('oauth.refresh_window', 300);

        return $expiresAt->lessThanOrEqualTo(Carbon::now()->addSeconds($window));
    }

    /**
     * The connection for one provider, or null.
     */
    public function connection(Business $business, OauthProvider $provider): ?OauthConnection
    {
        $this->tenantFor($business);

        return OauthConnection::query()
            ->where('provider', $provider)
            ->latest('id')
            ->first();
    }

    /**
     * Mark a connection unusable and tell the owner.
     *
     * FOUND-03: "On refresh failure: set status='expired', write provider_health,
     * dispatch a Reconnect notification." Expired rather than Revoked even when
     * the cause was a revocation, because the providers do not distinguish —
     * Google returns `invalid_grant` for both — and the remedy is identical.
     * ConnectionStatus::Revoked stays for the case we *do* know about: an owner
     * disconnecting deliberately.
     *
     * The tokens are cleared, not kept. They cannot work again, and a credential
     * with no possible use is pure liability.
     */
    public function markUnusable(OauthConnection $connection, string $reason): void
    {
        $connection->forceFill([
            'status' => ConnectionStatus::Expired,
            'access_token_enc' => null,
            'refresh_token_enc' => null,
            'last_error' => $reason,
        ])->save();

        $this->recordHealth($connection->provider, 'expired', $reason);
        $this->promptReconnect($connection->provider);
    }

    /**
     * Upsert the (business, provider) health row.
     *
     * `$error` is our own short reason code, never a provider response body.
     *
     * ⚠️ **A NULL `$tokenStatus` MEANS "LEAVE THE LIGHT WHERE THE LAST REAL
     * ANSWER LEFT IT", AND IT IS A STATE THIS METHOD ALREADY HAD** (7400) — the
     * `array_filter` below has always dropped nulls from the update, so the
     * change is that callers may now say it. It is the honest value for
     * anything that is not an answer *about this grant*: a vendor's own 5xx
     * says the vendor is broken, which is neither evidence that the grant works
     * nor evidence that it does not. Writing `'active'` there is a false
     * all-clear and writing `'error'` is a false accusation, and this table has
     * been on the wrong side of both.
     *
     * ⚠️ On a row that does not exist yet the column stays null, which is
     * representable and true: nothing has ever answered about this grant. The
     * one reader in `app/` asks only whether it is `quota_exhausted`.
     *
     * ⛔ **A FAILED VENDOR CALL SHOULD GO THROUGH {@see self::recordCallFailure()}
     * RATHER THAN PICKING A STATUS AT THE CALL SITE.** Two clients picked their
     * own and the rule drifted between them; there is one rule now and it has
     * one home.
     */
    public function recordHealth(
        OauthProvider $provider,
        ?string $tokenStatus,
        ?string $error = null,
        ?Carbon $syncedAt = null,
    ): ProviderHealth {
        $health = ProviderHealth::updateOrCreate(
            [
                'business_id' => Tenancy::idOrFail(),
                'provider' => $provider,
            ],
            array_filter([
                'token_status' => $tokenStatus,
                'last_error' => $error,
                'last_sync_at' => $syncedAt,
            ], static fn (mixed $value): bool => $value !== null),
        );

        // A clean run clears the previous error rather than leaving a stale one
        // on the health board, which would read as an ongoing problem.
        if ($error === null && $health->last_error !== null) {
            $health->forceFill(['last_error' => null])->save();
        }

        return $health;
    }

    /**
     * Record what a vendor's *failed answer* says about this tenant's grant —
     * which, for most of the failures a client sees, is nothing.
     *
     * ⛔ **A 5xx IS THE VENDOR SAYING IT IS BROKEN, AND IT WAS WRITTEN AS
     * `token_status = 'error'` ON THE TENANT'S GRANT** (7400). Google's own
     * documented 503 reasons are `backendError`, `notReady` and —
     * unimprovably — `backendNotConnected`, *"The request failed due to a
     * connection error"*, read from
     * https://developers.google.com/webmaster-tools/v1/errors on 2026-08-22.
     * **Google having a bad afternoon was filed as the customer's problem**, on
     * every tenant that happened to sync during the outage, in both clients
     * identically — while {@see self::refresh()}, twenty lines up this same
     * file, treated the same 503 as blameless and had a test explaining why.
     * The two paths disagreed about one vendor's one status code and neither
     * mentioned the other.
     *
     * ## The rule, stated once
     *
     *   4xx + a quota reason   `quota_exhausted`. Ours, and the only value the
     *                          one reader in `app/` reads.
     *   4xx                    `'error'`. The vendor answered *about us* — this
     *                          grant, this permission, this resource. That is
     *                          what the light is for.
     *   anything else          **null — the light is not touched.** A 5xx is the
     *                          vendor's own failure and a platform-wide fact; it
     *                          is not evidence about one tenant's grant in
     *                          either direction. The reason is still recorded,
     *                          because *"Google answered 503"* is a true
     *                          sentence about the far end, and `last_sync_at` is
     *                          left stale, which is the signal `28` §9.8's board
     *                          actually wants.
     *
     * ⚠️ **THIS IS NOT A NEW `token_status` VALUE, AND THAT IS DELIBERATE.**
     * 7270 proposed `vendor_unavailable` and blocked it on the table acquiring
     * a reader — refining the vocabulary of a row nothing reads is 314–316.
     * Not writing a false value needs no vocabulary at all, so the substance
     * of 7270 lands and the term it would have minted does not.
     *
     * ⚠️ **A TRANSPORT FAILURE NEVER REACHES HERE.** Both clients throw their
     * `unreachable()` before the response branch, and 7261 is why: when the
     * request never became a response there is nothing the far end said, so
     * there is nothing to record at all. This method is for the case where it
     * answered and the answer was a failure.
     *
     * @param  int  $status  The HTTP status the vendor returned.
     * @param  bool  $quota  The classifier's answer, not a re-derivation.
     * @param  string  $reason  Our own short code, never a vendor response body.
     */
    public function recordCallFailure(
        OauthProvider $provider,
        int $status,
        bool $quota,
        string $reason,
    ): ProviderHealth {
        $tokenStatus = match (true) {
            $quota => 'quota_exhausted',
            $status >= 400 && $status < 500 => 'error',
            default => null,
        };

        return $this->recordHealth($provider, $tokenStatus, $reason);
    }

    /**
     * Ask the owner to reconnect — once, not once per sweep.
     *
     * The refresh job runs continuously. Without this guard a provider that
     * stays broken over a weekend produces a notification every cycle, and the
     * owner learns to ignore the channel that matters most.
     */
    private function promptReconnect(OauthProvider $provider): void
    {
        $type = self::reconnectType($provider);

        $alreadyAsked = Notification::query()
            ->where('type', $type)
            ->whereNull('read_at')
            ->exists();

        if ($alreadyAsked) {
            return;
        }

        Notification::create([
            'type' => $type,
            'channel' => 'in_app',
            'title' => self::reconnectTitle($provider),
            'body' => 'Sign in again so we can keep working on your behalf. '
                .'Nothing else changes, and it takes about a minute.',
            'created_at' => Carbon::now(),
        ]);

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            null,
            ['provider' => $provider->value],
            self::reconnectTitle($provider),
        );
    }

    private function clearReconnectPrompts(OauthProvider $provider): void
    {
        Notification::query()
            ->where('type', self::reconnectType($provider))
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    private static function reconnectType(OauthProvider $provider): string
    {
        return 'reconnect_'.$provider->value;
    }

    /**
     * Outcome language: what the owner does, not what expired.
     *
     * "OAuth token refresh failed for provider google" is accurate and useless.
     *
     * ⛔ **THE `default` ARM WAS SHIPPING A RAW ENUM VALUE TO AN OWNER, AND
     * `Gsc` WAS REACHING IT — CORRECTED 2026-08-21 (7263).** This match had no
     * `Gsc` case, so a revoked Search Console grant produced the notification
     * title **"Reconnect your gsc account"** and an identical `OwnerActionNeeded`
     * entry on the activity screen. It was not a hypothetical arm:
     * `SyncSearchConsoleJob::classify()` calls `markUnusable()` on every 401 and
     * `tests/Feature/Visibility/SyncSearchConsoleJobTest.php` has asserted the
     * resulting `reconnect_gsc` row since the job was built — the row was
     * counted, never read.
     *
     * ⚠️ **The `default` arm stays, and stays as it is.** Seven of the eleven
     * providers still fall to it and none of them is demonstrably reachable
     * here today, so writing seven speculative strings would be seven claims
     * with no call site. What made this one worth fixing is that it is live and
     * that a tenant has no idea what "gsc" is; the next arm should be added the
     * same way, when something reaches it.
     */
    private static function reconnectTitle(OauthProvider $provider): string
    {
        return match ($provider) {
            OauthProvider::Google => 'Reconnect Google to keep your listing working',
            OauthProvider::Gsc => 'Reconnect Google Search Console to keep measuring your traffic',
            OauthProvider::Facebook, OauthProvider::Instagram => 'Reconnect Facebook to keep posting',
            OauthProvider::Microsoft => 'Reconnect Microsoft to keep sending email',
            default => 'Reconnect your '.$provider->value.' account',
        };
    }

    /**
     * Decrypt a stored column, treating tampered or foreign ciphertext as absent.
     *
     * A DecryptException here means the value was not written by this
     * application's key — a restored database from another environment, a
     * rotated APP_KEY, or tampering. All three mean the same thing operationally:
     * there is no usable token, ask the owner to reconnect. Rethrowing would take
     * the whole job down and put the ciphertext in a stack trace.
     */
    private function decrypt(?string $ciphertext): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * The established tenant, asserted to be the business the caller named.
     */
    private function tenantFor(Business $business): int
    {
        $tenantId = Tenancy::idOrFail();

        if ($business->getKey() !== $tenantId) {
            throw new TenantMismatch($tenantId, $business->getKey());
        }

        return $tenantId;
    }
}
