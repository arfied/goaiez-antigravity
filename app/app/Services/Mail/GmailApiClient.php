<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Support\PlatformCredentials;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The platform's own Google Workspace mailbox, reached through the Gmail API.
 *
 * Email delivery architecture and decision 2093: an **internal** OAuth app on the goaiez Workspace
 * domain, which needs no Google verification review and is therefore approved
 * the moment it is created. That is the entire reason it beat SES for the soft
 * launch, and 2093 records both sides — the owner's own architect had ruled two
 * turns earlier that *"Google Workspace cannot be the volume transport"*.
 *
 * ## Verified against the live reference, not from memory
 *
 * Read 2026-08-11 at
 * `developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/send`
 * (page dated 15 April 2026) and at Google's own sending-limits page:
 *
 *   - `POST /gmail/v1/users/{userId}/messages/send` on `gmail.googleapis.com`.
 *   - The body is a `Message`: `{"raw": "<the whole RFC 2822 message>"}`.
 *   - `raw` is **base64url**, not base64. Standard base64 contains `+` and `/`,
 *     which are not URL-safe and which Google rejects — the failure is a 400
 *     on some messages and not others, depending on the bytes.
 *   - Scope `https://www.googleapis.com/auth/gmail.send` is sufficient. Three
 *     wider scopes also authorise it and every one of them grants read access
 *     to the whole mailbox.
 *
 * ⚠️ **`CLAUDE.md` RECORDS FOUR TIMES THIS CODEBASE GOT A VENDOR STRING WRONG
 * FROM MEMORY** (255, 277, 684, 1349), which is why the paragraph above exists
 * and why the endpoint is in `config/platform_mail.php` rather than inline.
 *
 * ## The ceiling this transport brings with it
 *
 * 2,000 messages per day per user, over a **rolling 24-hour window**, and
 * exceeding it stops the account accepting mail for **up to 24 hours**. The
 * Gmail API path additionally caps one message at 500 recipients. Those are
 * facts about the account, not about this class — `MailQuota` is where
 * they are enforced, because they apply to every message the account sends
 * including ones this application did not make.
 *
 * ⛔ **BOUNCES DO NOT COME BACK THROUGH THIS API AND COMPLAINTS DO NOT COME
 * BACK AT ALL.** The send returns a `Message` with an id and nothing about
 * delivery; a failure arrives later as a delivery-status notification *email*
 * to the sending mailbox, and Google runs no per-recipient feedback loop for
 * Workspace senders. That is 2094, and it is why `MailFeedbackSignal` exists
 * and why this mailer is `ndr_only`.
 */
final class GmailApiClient
{
    /**
     * How long an access token is cached.
     *
     * Google's tokens last an hour; fifty minutes leaves a margin for a call
     * that starts just before expiry. Cached rather than refreshed per send
     * because a 2,000-message day would otherwise make 2,000 token calls, and
     * Google rate-limits that endpoint too.
     */
    private const int TOKEN_TTL_SECONDS = 3000;

    private const string TOKEN_CACHE_KEY = 'platform-mail:gmail:access-token';

    /**
     * The support account's own cache key.
     *
     * ⛔ **A SECOND KEY BECAUSE IT IS A SECOND ACCOUNT, NOT A SECOND CACHE OF
     * THE SAME THING.** Sharing one key would hand a `gmail.readonly` token to
     * the relay path — or a `gmail.metadata` one to the support poller, which
     * would 403 on every body — depending only on which ran first.
     */
    private const string SUPPORT_TOKEN_CACHE_KEY = 'platform-mail:gmail-support:access-token';

    /**
     * Send one already-rendered RFC 2822 message.
     *
     * @return string the Gmail message id
     *
     * @throws RuntimeException when Google refuses the message
     */
    public function send(string $rfc2822): string
    {
        $endpoint = (string) config('platform_mail.gmail.send_endpoint');
        $user = (string) config('platform_mail.gmail.user');
        $timeout = (int) config('platform_mail.gmail.timeout', 15);

        $response = Http::withToken($this->accessToken())
            ->timeout($timeout)
            ->asJson()
            ->post($endpoint.'/'.rawurlencode($user).'/messages/send', [
                // ⚠️ BASE64URL. `strtr` of the two offending characters and the
                // padding stripped — Google accepts unpadded base64url and
                // rejects `+` and `/`.
                'raw' => rtrim(strtr(base64_encode($rfc2822), '+/', '-_'), '='),
            ]);

        if ($response->failed()) {
            // ⚠️ **THE STATUS AND GOOGLE'S OWN `error.message`, NEVER THE WHOLE
            // BODY.** A Gmail error body can quote the message it refused,
            // including the recipient — and this string reaches `failed_jobs`
            // and the log. `DeliverPlatformMail` already keeps the address out
            // of its own line for the same reason.
            $message = $response->json('error.message');

            throw new RuntimeException(
                'Gmail refused the message with HTTP '.$response->status()
                .(is_string($message) ? ': '.$message : '.')
            );
        }

        $id = $response->json('id');

        if (! is_string($id) || $id === '') {
            throw new RuntimeException(
                'Gmail accepted the message but named no id, so nothing can be traced back to '
                .'this send. Treated as a failure rather than a silent success.'
            );
        }

        return $id;
    }

    /**
     * A live access token for the internal app's grant.
     *
     * ⚠️ **THE REFRESH TOKEN IS THE PLATFORM'S OWN AND COMES FROM
     * `PlatformCredentials`, NEVER FROM THE TENANT VAULT** (2072). The vault
     * holds a *tenant's* connection to *their* mailbox; this is our Workspace
     * account, used on a path where no tenant is resolved. Conflating the two is
     * the mistake `CLAUDE.md` says must not be made on the strength of a shared
     * vendor name, and it would mean platform mail riding a tenant mailbox.
     *
     * ⚠️ **PUBLIC SINCE THE INBOUND HALF LANDED, AND SHARING IT IS THE POINT.**
     * {@see GmailInbox} reads the same mailbox on the same grant — one refresh
     * token, two scopes on it (`gmail.send` and `gmail.metadata`). A second
     * token cache would make 2,000 extra calls to an endpoint Google rate-limits
     * separately, and would leave two places holding a credential instead of
     * one. What this method must never become is a general accessor: it is the
     * *platform's* token, and nothing tenant-facing may reach it.
     */
    public function accessToken(): string
    {
        return $this->exchange('gmail_refresh_token', self::TOKEN_CACHE_KEY);
    }

    /**
     * A live access token for the **support** account's grant (T176 P24).
     *
     * ⛔ **A DIFFERENT WORKSPACE ACCOUNT, NOT A DIFFERENT SCOPE ON THE SAME
     * ONE.** {@see GmailInbox} rests its whole privacy argument on the relay
     * account holding `gmail.metadata`, under which a message body *cannot* be
     * fetched. Reading support mail needs the body, so the support account is
     * authorised on its own with `gmail.readonly` and keeps its own refresh
     * token — and the relay's guarantee survives, because a refresh token
     * carries the scopes granted to one account.
     *
     * ⚠️ **THE ONE WAY TO BREAK THAT IS TO PASTE THE SAME REFRESH TOKEN INTO
     * BOTH CREDENTIALS**, which no code here can see. What is guarded in code is
     * the neighbouring half of the same mistake: `SupportMailbox` refuses to run
     * when the two mailbox *names* match.
     */
    public function supportAccessToken(): string
    {
        return $this->exchange('gmail_support_refresh_token', self::SUPPORT_TOKEN_CACHE_KEY);
    }

    /**
     * One refresh-token exchange, for one account.
     *
     * @param  string  $credential  the `PlatformCredentials` key holding the refresh token
     */
    private function exchange(string $credential, string $cacheKey): string
    {
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()
            ->timeout((int) config('platform_mail.gmail.timeout', 15))
            ->post((string) config('platform_mail.gmail.token_endpoint'), [
                'client_id' => PlatformCredentials::get('gmail_client_id'),
                'client_secret' => PlatformCredentials::get('gmail_client_secret'),
                'refresh_token' => PlatformCredentials::get($credential),
                'grant_type' => 'refresh_token',
            ]);

        if ($response->failed()) {
            // ⚠️ NO BODY AT ALL HERE. A token-endpoint error body echoes the
            // request, and the request contains the client secret and the
            // refresh token — the two values that must never reach a log. The
            // credential *name* is safe and is what an operator needs.
            throw new RuntimeException(
                'Google refused the platform mail refresh token with HTTP '.$response->status()
                .". Re-authorise the internal app in Ops; the credential is [{$credential}]."
            );
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Google returned no access token for the platform mailbox.');
        }

        Cache::put($cacheKey, $token, self::TOKEN_TTL_SECONDS);

        return $token;
    }
}
