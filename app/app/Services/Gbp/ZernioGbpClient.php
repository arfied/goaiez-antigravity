<?php

declare(strict_types=1);

namespace App\Services\Gbp;

use App\Contracts\GbpClient;
use App\Exceptions\GbpRequestFailed;
use App\Http\Controllers\Gbp\GbpConnectController;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlatformCredentials;
use App\Support\VendorLog;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Google Business Profile, read through Zernio.
 *
 * Endpoints, headers, parameters and the error envelope read from Zernio's live
 * documentation on 2026-08-04. Three things that reading rather than remembering
 * caught, each of which a from-memory implementation gets wrong:
 *
 *   - **Reviews are not under the Google Business namespace.** They are a
 *     cross-platform inbox resource, `GET /inbox/reviews`, filtered with
 *     `platform=googlebusiness`. Every `platforms/google-business/*` page —
 *     including the one titled "Business Profile Management" — documents menus,
 *     media, attributes and verifications and **no review endpoint at all**.
 *     Looking where the capability logically belongs finds nothing and invites
 *     the conclusion that reviews are unsupported.
 *   - **The star rating is an integer over 0–5, not Google's `STAR_RATING`
 *     enum.** Zernio normalises it, and the zero is real — see {@see GbpReview}.
 *     Code written against Google's own API shape would look for `"FIVE"`.
 *   - **`limit` maxes at 50.** Values above it are a documented range violation
 *     rather than a silently clamped request, so a caller asking for 200 gets a
 *     422 for the whole page instead of 50 reviews.
 *
 * WHY THIS DOES NOT EXTEND ProviderClient, which is where a Google client would
 * be expected to live: that base class exists to guarantee every call carries a
 * *tenant's* OAuth token from the vault, and takes a `Business` to do it. Here
 * the credential is **ours** — one platform API key — and the tenant's Google
 * authorisation is held by Zernio, not by us. Inheriting it would mean fetching
 * a token that plays no part in the request.
 *
 * ⚠️ **WHAT THE TENANT ACTUALLY GRANTS, since it is not visible from this file:**
 * connecting sends them through Zernio's OAuth consent screen, which requests
 * business.manage together with userinfo.profile and userinfo.email in a single
 * flow — Zernio's docs are explicit that the scopes cannot be requested
 * individually. business.manage is read **and write** on the listing, so from
 * the moment a tenant connects, Zernio can post to and edit their profile
 * whether or not this application ever calls it. That is a subprocessor
 * relationship that begins at their consent screen rather than at our HTTP
 * client, and `docs/SUBPROCESSOR-INVENTORY.md` §2 records why it needs a DPA
 * before the first connection rather than before the first call.
 *
 * ⚠️ **AND THAT MEANS THIS CLASS IS STILL NOT TENANT-SCOPED. READ THIS BEFORE
 * CALLING IT.** `$accountRef` is opaque and unvalidated: nothing here can tell
 * whether the account id it was handed belongs to the tenant on whose behalf it
 * is being called. That has not changed and cannot be fixed inside this class.
 *
 * ✅ **What has changed is that the binding now exists.** Decision 531 said the
 * first job of whatever gave this a caller was a tenant-scoped store mapping a
 * location to its account — `gbp_connections` is that store, RLS-`FORCE`d and
 * globally scoped, and {@see GbpConnections} is the only thing in `app/` that
 * reads or writes it. **Take account refs from there and from nowhere else.** In
 * particular they never come through from a request: the ref arrives on a
 * redirect from Zernio, and the column is guarded on the model precisely so that
 * the one value deciding whose reviews we read cannot be mass-assigned.
 *
 * WHAT IT KEEPS FROM ProviderClient, deliberately: Laravel's HTTP client so
 * `Http::fake()` and `preventStrayRequests()` work — decision 277's reasoning,
 * where an official SDK's own Guzzle would be invisible to both and to `40`
 * Part 8's outbound lint; `VendorLog` so the call shape is logged and the
 * payload never is; and no synchronous retry — one attempt, then a classified
 * failure, with retrying left to the job that has backoff.
 */
final class ZernioGbpClient implements GbpClient
{
    private const string BASE = 'https://zernio.com/api/v1';

    /**
     * The one platform key this client runs on.
     *
     * ⛔ **NAMED ONCE BECAUSE IT IS NOW ASKED TWICE** — `has()` in
     * {@see self::assertUsable()} and `get()` in {@see self::request()}. A guard
     * that checks a different key from the one that is read is worse than no
     * guard, because it reads as considered.
     */
    private const string CREDENTIAL = 'zernio_api_key';

    /**
     * Zernio's slug for Google Business, in one place because it appears in a
     * query filter and in a path segment and the two must not drift.
     *
     * ⚠️ **It has no underscore, and the plausible spelling is the wrong one.**
     * Their OpenAPI document uses `googlebusiness` in all 28 places it appears
     * and `google_business` in none — checked against the raw specification on
     * 2026-08-07, because a summary of that same document rendered it as
     * `google_business`. Sent wrong it is not an error: the reviews filter
     * simply matches nothing, and a location with reviews reports zero forever.
     */
    private const string PLATFORM = 'googlebusiness';

    /**
     * Zernio's documented maximum for `limit`.
     *
     * Not a registry key: it is a fact about somebody else's API, in the same
     * class as a field mask's shape rather than a threshold we chose. A registry
     * key here would let an operator "raise" a ceiling that belongs to a third
     * party and get a 422 for the whole page.
     *
     * The *default* page size is deliberately not a constant here. It is the
     * interface's own parameter default, and holding it in both places would
     * mean two numbers that can disagree — with the one a caller actually gets
     * decided by which file they read.
     */
    private const int MAX_LIMIT = 50;

    /**
     * The libcurl codes that establish the request never left this process.
     *
     * ⛔ **TWO, AND THE SHORTNESS OF THE LIST IS THE POINT** (7002). Read
     * verbatim from `https://curl.se/libcurl/c/libcurl-errors.html`, the raw
     * page rather than a summary, `last-modified: Tue, 04 Aug 2026 08:57:36
     * GMT`, fetched 2026-08-21:
     *
     *   6  `CURLE_COULDNT_RESOLVE_HOST` — *"Could not resolve host. The given
     *      remote host was not resolved."* There is no address, so there is no
     *      socket and nothing was written to one.
     *   7  `CURLE_COULDNT_CONNECT` — *"Failed to connect() to host or proxy."*
     *      `connect()` did not return, so no request bytes exist.
     *
     * ⚠️ **WHAT IS DELIBERATELY ABSENT MATTERS MORE THAN WHAT IS PRESENT**, and
     * each exclusion is an argument rather than an oversight:
     *
     *   28 `CURLE_OPERATION_TIMEDOUT` — *"Operation timeout. The specified
     *      time-out period was reached according to the conditions."* ⚠️ **That
     *      wording covers a connect timeout as well as a read timeout, and the
     *      artefact does not distinguish them**: `CURLOPT_CONNECTTIMEOUT`'s own
     *      page names no error code at all (fetched 2026-08-21). So a request
     *      that timed out *before* connecting is indistinguishable here from
     *      one that timed out waiting for the reply, and the message text is
     *      the only thing that differs — which is a third party's wording, and
     *      narrowing a publication guard on it is the change 6830 forbids.
     *   52 `CURLE_GOT_NOTHING` — *"Nothing was returned from the server"*: the
     *      server was reached.
     *   35 `CURLE_SSL_CONNECT_ERROR` — the TCP connection stood and the TLS
     *      handshake failed. Usually no application bytes were written, and
     *      *usually* is not *provably*: TLS 1.3 early data and post-handshake
     *      failures both put application data on the wire first.
     *   5  `CURLE_COULDNT_RESOLVE_PROXY` — genuinely never left, and no proxy
     *      is configured anywhere in this application, so the arm has no
     *      instance to fire on (256: ship the branch that can fire today).
     *
     * ⛔ **AND THE CATCH IS FAR WIDER THAN GUZZLE'S FIVE-MEMBER CONNECTION MAP**
     * (7003). `vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php:1082` turns
     * 28, 6, 7, 35 and 52 into a `ConnectException` — but
     * `Illuminate\Http\Client\PendingRequest` raises
     * `ConnectionException` at **four** sites, and
     * `marshalRequestExceptionWithoutResponse()` funnels **every**
     * `RequestException` with no response into the same class. That is every
     * other libcurl code — 18 `PARTIAL_FILE`, 55 `SEND_ERROR`, 56 `RECV_ERROR`,
     * 92 `HTTP2_STREAM` — plus *"An error was encountered while creating the
     * response"* and the `on_headers` path. **Nearly all of those mean the
     * request did leave**, which is why this list is an allowlist of two rather
     * than a denylist of three.
     */
    private const array NEVER_LEFT = [6, 7];

    /**
     * The reason recorded when a transport failure may have reached the vendor.
     *
     * Unchanged from what all four catch sites passed before 7000, deliberately:
     * it is the safe default, and a reader diffing an old `automation_runs` row
     * against a new one should see the same token for the same outcome.
     */
    private const string REASON_UNKNOWN = 'connection_failed';

    /**
     * The reason recorded when the request provably never left this process.
     */
    private const string REASON_NEVER_LEFT = 'connection_never_established';

    public function __construct(
        private readonly DefaultsRegistry $defaults,
    ) {}

    public function reviews(string $accountRef, ?string $cursor = null, int $limit = 25): GbpReviewPage
    {
        $this->assertUsable();

        $response = $this->get('/inbox/reviews', array_filter([
            'accountId' => $accountRef,
            'platform' => self::PLATFORM,
            'limit' => max(1, min($limit, self::MAX_LIMIT)),
            'cursor' => $cursor,
            'sortBy' => 'date',
            'sortOrder' => 'desc',
        ], static fn (mixed $v): bool => $v !== null), accountScoped: true);

        $payload = $response->json('data');

        // The envelope has moved before and will again; a page whose list is
        // unreadable is an empty last page, never an exception. Abandoning a
        // sync because a wrapper key was renamed loses every review behind it.
        if (! is_array($payload)) {
            $payload = $response->json('reviews');
        }

        $reviews = [];
        $dropped = 0;

        foreach (is_array($payload) ? $payload : [] as $entry) {
            if (! is_array($entry)) {
                $dropped++;

                continue;
            }

            $review = GbpReview::fromZernio($entry);

            $review === null ? $dropped++ : $reviews[] = $review;
        }

        $cursor = $response->json('pagination.nextCursor') ?? $response->json('nextCursor');

        return new GbpReviewPage(
            reviews: $reviews,
            cursor: is_string($cursor) && $cursor !== '' ? $cursor : null,
            dropped: $dropped,
        );
    }

    public function connectionHealthy(string $accountRef): bool
    {
        $this->assertUsable();

        try {
            $response = $this->get('/accounts/'.rawurlencode($accountRef).'/health', accountScoped: true);
        } catch (GbpRequestFailed $e) {
            // A dead connection is the answer to this question, not a failure to
            // answer it. Anything else — our key, a quota wall, an outage — is
            // still a failure and still propagates, because reporting "the
            // tenant disconnected" when Zernio is down would send an owner to
            // re-authorise a connection that was never broken.
            if ($e->disconnected) {
                return false;
            }

            throw $e;
        }

        $status = $response->json('status') ?? $response->json('data.status');

        // Fails closed on an unreadable answer. The caller's next move is
        // `handoff()`, which is the safe direction: a human looks at a
        // connection that was fine, rather than an automation assuming a broken
        // one is healthy and posting nothing to a profile nobody is watching.
        return is_string($status) && in_array(strtolower($status), ['healthy', 'ok', 'active', 'connected'], true);
    }

    /**
     * Publish the owner's reply, through the inbox resource the reader uses.
     *
     * `POST /v1/inbox/reviews/{reviewId}/reply`, body `{accountId, message}` —
     * confirmed against Zernio's published OpenAPI document on 2026-08-11, not
     * against their marketing pages, which is the distinction decision 534 made
     * and the reason it refused to guess this method into existence.
     *
     * ⚠️ **`rawurlencode()` IS THE LOAD-BEARING LINE IN THIS METHOD.** Their
     * `GET /inbox/reviews` documents its `id` as *"for Google Business this is
     * the full review resource name
     * (`accounts/{accountId}/locations/{locationId}/reviews/{reviewId}`), so it
     * also encodes the location"* — six slashes — and the reply endpoint's own
     * path parameter is documented as *"Review ID (URL-encoded for Google
     * Business)"*. Interpolated raw, the request goes to
     * `/inbox/reviews/accounts/123/locations/456/reviews/789/reply`, which is a
     * different route entirely. **The failure is a 404 on every reply, forever,
     * for every tenant** — and it would read as "Zernio does not support this"
     * rather than as an escaping bug, which is how decision 534's conclusion
     * gets reached a second time.
     *
     * ⚠️ **AND `accountScoped: true` IS NOT COSMETIC HERE EITHER** (532). This
     * call carries a tenant's account ref, so a 403 attributed to Google means
     * *that tenant revoked us*, while a 403 with no platform attribution means
     * *our plan does not include the Inbox addon* — which the reply endpoint
     * documents as its own 403 and which is a bill of ours, not a reconnection
     * prompt for every owner on the platform.
     *
     * Both fields of the receipt are read defensively: a relay that changes its
     * envelope must not turn a reply that was published into an exception, and
     * the receipt is evidence rather than control flow.
     *
     * @throws GbpRequestFailed
     */
    public function replyToReview(string $accountRef, string $externalReviewId, string $comment): GbpReplyReceipt
    {
        $this->assertUsable();

        if (trim($comment) === '') {
            // Refused here rather than sent. An empty reply is not a smaller
            // reply — it is a blank comment published under a business's name,
            // and the overwrite semantics above mean it would erase whatever
            // they last said. Not retryable: the same empty string comes back.
            throw GbpRequestFailed::unreadable('reply_comment_empty');
        }

        $url = self::BASE.'/inbox/reviews/'.rawurlencode($externalReviewId).'/reply';

        try {
            $response = VendorLog::timed(
                'zernio',
                'POST',
                $url,
                fn (): Response => $this->request()->post($url, [
                    'accountId' => $accountRef,
                    'message' => $comment,
                ]),
            );
        } catch (ConnectionException $e) {
            $failure = self::transportFailure($e);

            VendorLog::failure('zernio', 'POST', $url, $failure->reason);

            throw $failure;
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $replyId = $response->json('reply.id');
        $status = $response->json('status');

        return new GbpReplyReceipt(
            providerReplyId: is_string($replyId) && $replyId !== '' ? $replyId : null,
            providerStatus: is_string($status) && $status !== '' ? $status : null,
        );
    }

    /**
     * @throws GbpRequestFailed
     */
    public function publishPost(string $accountRef, string $content, string $idempotencyKey, array $metadata = []): GbpPostReceipt
    {
        $this->assertUsable();

        if (trim($content) === '') {
            throw GbpRequestFailed::unreadable('post_content_empty');
        }

        $url = self::BASE.'/posts';

        try {
            $response = VendorLog::timed(
                'zernio',
                'POST',
                $url,
                fn (): Response => $this->request()->withHeaders(['Idempotency-Key' => $idempotencyKey])->post($url, [
                    'content' => $content,
                    'platforms' => [
                        [
                            'platform' => self::PLATFORM,
                            'accountId' => $accountRef,
                        ],
                    ],
                    'publishNow' => true,
                    'metadata' => (object) $metadata,
                ]),
            );
        } catch (ConnectionException $e) {
            $failure = self::transportFailure($e);

            VendorLog::failure('zernio', 'POST', $url, $failure->reason);

            throw $failure;
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }

        $postId = $response->json('post._id');
        $status = $response->json('post.status');
        $platformPostId = $response->json('post.platforms.0.platformPostId');
        $errorMessage = $response->json('post.platforms.0.errorMessage');

        return new GbpPostReceipt(
            providerPostId: is_string($postId) && $postId !== '' ? $postId : null,
            status: is_string($status) ? $status : 'unknown',
            platformPostId: is_string($platformPostId) && $platformPostId !== '' ? $platformPostId : null,
            errorMessage: is_string($errorMessage) && $errorMessage !== '' ? $errorMessage : null,
        );
    }

    /**
     * The id of the Zernio profile called `$name`, if one exists.
     *
     * Their `name` filter is documented as exact-match, and as the way to
     * *"recover a profile id after an ambiguous create (timeout followed by a
     * 409 on retry)"* — so this is the vendor's own recovery path rather than
     * one invented here.
     *
     * @throws GbpRequestFailed
     */
    public function profileRefByName(string $name): ?string
    {
        $this->assertUsable();

        $response = $this->get('/profiles', ['name' => $name, 'limit' => 1, 'skip' => 0]);

        $id = $response->json('profiles.0._id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Create a Zernio profile, or return the one that already has this name.
     *
     * ⚠️ **THREE OUTCOMES, NOT TWO, AND THE THIRD IS THE ONE THAT LOSES A
     * PROFILE.** A create that times out may well have succeeded, and Zernio
     * names profiles uniquely per workspace — so a naive retry gets a 409 and,
     * without reading its body, would look like a permanent failure while a
     * chargeable profile sits orphaned in their workspace. Two documented
     * mechanisms are used together rather than one: an `Idempotency-Key`, which
     * makes a retry replay the original 201 with the same `_id`, and the 409's
     * own `details.existingProfileId`, which resolves the case where the key has
     * aged out or the profile was made by an earlier call.
     *
     * ⚠️ **A 403 here is `Profile limit exceeded` — our plan, not the tenant's
     * connection.** It reaches the owner as "we could not start this", never as
     * "reconnect Google", on decision 532's distinction.
     *
     * @param  string  $idempotencyKey  Derived from the business, never random:
     *                                  a fresh key on every attempt is the same
     *                                  as sending none.
     *
     * @throws GbpRequestFailed
     */
    public function createProfile(string $name, string $idempotencyKey): string
    {
        $this->assertUsable();

        $url = self::BASE.'/profiles';

        try {
            $response = VendorLog::timed(
                'zernio',
                'POST',
                $url,
                fn (): Response => $this->request()
                    ->withHeaders(['Idempotency-Key' => $idempotencyKey])
                    ->post($url, ['name' => $name]),
            );
        } catch (ConnectionException $e) {
            $failure = self::transportFailure($e);

            VendorLog::failure('zernio', 'POST', $url, $failure->reason);

            throw $failure;
        }

        if ($response->status() === 409) {
            $existing = $response->json('details.existingProfileId');

            if (is_string($existing) && $existing !== '') {
                return $existing;
            }

            // The other 409: a request with this key is still in flight. It is
            // the queue's to retry, so it must not be classified as terminal —
            // and `from()` would, because 409 is neither 429 nor a 5xx.
            throw GbpRequestFailed::stillProcessing();
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response);
        }

        $id = $response->json('profile._id');

        if (! is_string($id) || $id === '') {
            // A 201 we cannot read the id out of is not a success we can store.
            // Failing here rather than writing a null ref is what stops a
            // connection row pointing at a profile nobody can name.
            throw GbpRequestFailed::unreadable('profile_id_missing');
        }

        return $id;
    }

    /**
     * Where to send an owner so they can authorise Google.
     *
     * Standard mode, not headless: Zernio hosts the consent screen *and* the
     * location picker, and a Google Business account can own many locations, so
     * headless would mean rebuilding their picker against
     * `/connect/googlebusiness/locations` — a second screen, a `pendingDataToken`
     * to hold, and a search box for accounts with more locations than one page.
     * None of that buys anything the owner can see.
     *
     * ⚠️ **`$redirectUrl` IS THE ONLY THING TYING THE ANSWER BACK TO US**, which
     * is why the caller signs it. Zernio appends its result parameters to
     * whatever URL is passed — `connected`, `profileId`, `accountId`, `username`
     * — and carries no state of ours through the flow. See
     * {@see GbpConnectController} for what a bare,
     * unsigned callback would have allowed.
     *
     * @throws GbpRequestFailed
     */
    public function connectUrl(string $profileRef, string $redirectUrl): string
    {
        $this->assertUsable();

        $response = $this->get('/connect/'.self::PLATFORM, [
            'profileId' => $profileRef,
            'redirect_url' => $redirectUrl,
        ]);

        $url = $response->json('authUrl');

        if (! is_string($url) || $url === '') {
            throw GbpRequestFailed::unreadable('auth_url_missing');
        }

        return $url;
    }

    /**
     * Does this profile own this account? The one question the redirect cannot answer.
     *
     * ⛔ **THIS IS THE ONLY CHECK ON THE CONNECT CALLBACK WHERE ONE SIDE IS NOT
     * SUPPLIED BY WHOEVER SENT THE CALLBACK** (6600–6604). Zernio appends its
     * result to whatever `redirect_url` we hand it and passes no state of ours
     * through the round trip, so `accountId` **and** `profileId` are both values
     * the browser arrives holding — comparing one against the other, or either
     * against a stale tab, compares attacker-supplied data with
     * attacker-supplied data. `profileId` is not a secret and not a
     * capability: it is our own profile reference, echoed back. **The account
     * reference is the single value deciding whose Google reviews this
     * application reads**, and until this method existed nothing anywhere
     * established that the one in the callback had ever been connected by the
     * business completing it.
     *
     * `GET /v1/accounts?profileId=…` is Zernio's own tenant filter — their
     * multi-tenant guide calls a profile *"the tenant boundary"* — and
     * `SocialAccount` declares both `_id` and `profileId` **required**, so the
     * answer is not conditional on a documentation detail that moved three
     * weeks ago. Read from `docs.zernio.com/accounts/list-accounts` and
     * `/multi-tenant` on **2026-08-21**.
     *
     * ⚠️ **`includeOverLimit` IS LOAD-BEARING AND ITS ABSENCE FAILS SILENTLY.**
     * The endpoint documents itself as *"Only includes accounts within the plan
     * limit by default"*, so on the day the platform's Zernio plan is full this
     * list would omit a genuinely connected account and every affected owner
     * would be told their own Google account is not theirs. It is sent as the
     * string `'true'` rather than a PHP `true`, which Laravel would serialise as
     * `1`; their parameter is documented as a boolean and `'true'` is the
     * spelling their own examples use.
     *
     * ⚠️ **PAGINATION IS DELIBERATELY NOT SENT.** *"page and limit must be
     * supplied together … Omit both for all accounts."* Sending a page size
     * would put a silent 401st-account hole in a tenant boundary; omitting both
     * is documented to return the whole filtered set.
     *
     * ⚠️ **AN UNREADABLE ANSWER IS A FAILURE, NEVER A `false`.** `false` here
     * refuses an owner who has just granted `business.manage` at Google, so it
     * has to mean *"Zernio says no"* and never *"we could not tell"* — the same
     * distinction {@see connectionHealthy()} draws, for the same reason.
     *
     * @throws GbpRequestFailed
     */
    public function profileOwnsAccount(string $profileRef, string $accountRef): bool
    {
        $this->assertUsable();

        $response = $this->get('/accounts', [
            'profileId' => $profileRef,
            'platform' => self::PLATFORM,
            'includeOverLimit' => 'true',
        ]);

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

    /**
     * Every account connected under our platform key — the invoice, listed.
     *
     * ⛔ **THIS IS THE UNFILTERED LIST, AND THE CLASS DOCBLOCK IN
     * {@see GbpConnections} SPENT A PARAGRAPH SAYING NOTHING HERE READS IT.**
     * That paragraph is right about what the list *is* — every account every
     * tenant has connected, under one key of ours — and 6606 has already
     * corrected the half of it that generalised into *"and therefore nothing
     * may read it"*. What stands is the narrower rule, and this method is
     * written to obey it: **the list may be reconciled and may never be
     * rendered.** {@see ZernioReconciliation} is the only caller, it renders a
     * business number or a profile reference and never an account id, and
     * {@see ZernioAccount} deliberately declines to carry the two fields on the
     * payload that are somebody's name.
     *
     * ⚠️ **WHY THE UNFILTERED CALL RATHER THAN ONE SCOPED CALL PER PROFILE WE
     * KNOW OF.** The question being asked is *what are we billed for* — and an
     * account on a profile this application has no record of is exactly the
     * account that is billed and invisible. Asking profile by profile can only
     * ever return accounts we already knew to look for, which answers the
     * question by assuming it away.
     *
     * ⚠️ **AND NO `platform` FILTER, FOR THE SAME REASON ONE STEP OUT.** Zernio
     * bills per connected account per day whatever network it is (4720), and a
     * team holds every profile and every platform (6771). Filtering to
     * `googlebusiness` would hide a billed account behind a filter chosen for
     * tidiness. Each account reports its own platform instead.
     *
     * ⚠️ **`includeOverLimit` IS LOAD-BEARING HERE FOR A SECOND REASON.**
     * 6603 needs it so a full plan cannot tell an owner their own account is
     * not theirs; this method needs it because an account over the plan limit
     * is connected, is billed, and would otherwise be missing from precisely
     * the count that exists to notice accounts we are paying for. Sent as the
     * string `'true'`, which is the spelling their own examples use.
     *
     * ⚠️ **PAGINATION IS DELIBERATELY NOT SENT** — *"page and limit must be
     * supplied together … Omit both for all accounts."* A page size here would
     * silently truncate the bill.
     *
     * ⚠️ **AN UNREADABLE ENVELOPE IS A FAILURE, NEVER AN EMPTY LIST.** An empty
     * list means *"nothing is connected and nothing is owed"*, which is the one
     * conclusion a broken reconciliation must never reach on its own — 4720's
     * permanently-zero meter, arriving through a `catch`.
     *
     * @throws GbpRequestFailed
     */
    public function connectedAccounts(): ZernioAccountList
    {
        $this->assertUsable();

        $response = $this->get('/accounts', [
            'includeOverLimit' => 'true',
        ]);

        $accounts = $response->json('accounts');

        if (! is_array($accounts)) {
            throw GbpRequestFailed::unreadable('accounts_missing');
        }

        $readable = [];
        $unreadable = 0;

        foreach ($accounts as $account) {
            if (! is_array($account)) {
                $unreadable++;

                continue;
            }

            $ref = $account['_id'] ?? null;

            if (! is_string($ref) || $ref === '') {
                $unreadable++;

                continue;
            }

            $profile = $account['profileId'] ?? null;
            $platform = $account['platform'] ?? null;

            $readable[] = new ZernioAccount(
                accountRef: $ref,
                profileRef: is_string($profile) && $profile !== '' ? $profile : null,
                platform: is_string($platform) && $platform !== '' ? $platform : null,
            );
        }

        return new ZernioAccountList($readable, $unreadable);
    }

    /**
     * End Zernio's access to a connected Google account.
     *
     * ⚠️ **THIS CALL IS WHAT MAKES "DISCONNECT" TRUE, AND OMITTING IT WOULD BE A
     * DISCLOSURE FAILURE RATHER THAN A MISSING FEATURE.** Deleting our own row
     * stops *us* reading. It does nothing to the grant, and the grant is
     * `business.manage` — read **and write** on the tenant's listing, held by
     * Zernio's OAuth application from the moment the owner consented. Tenants
     * have no Zernio dashboard of their own, so if this application does not
     * revoke it, nobody can: the owner would be shown "Disconnected" while a
     * third party kept standing access to their Google Business Profile.
     *
     * A 404 is success. It means the account is already gone — which is the
     * state being asked for, and treating it as a failure would leave an owner
     * unable to finish disconnecting the one connection that no longer exists.
     *
     * @throws GbpRequestFailed
     */
    public function disconnectAccount(string $accountRef): void
    {
        $this->assertUsable();

        $url = self::BASE.'/accounts/'.rawurlencode($accountRef);

        try {
            $response = VendorLog::timed(
                'zernio',
                'DELETE',
                $url,
                fn (): Response => $this->request()->delete($url),
            );
        } catch (ConnectionException $e) {
            $failure = self::transportFailure($e);

            VendorLog::failure('zernio', 'DELETE', $url, $failure->reason);

            throw $failure;
        }

        if ($response->status() === 404) {
            return;
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, accountScoped: true);
        }
    }

    /**
     * Refuse to run while the integration is switched off.
     *
     * The flag is checked here rather than at each call site so that a new
     * method cannot forget it, and it defaults **off** — a live vendor
     * integration that switches itself on because somebody deployed a branch is
     * not a dark launch. Flipping it is a `platform_settings` row, so enabling
     * and disabling are both operations rather than deploys, which is what
     * decision 193 established for the audit budget and what makes an incident
     * response one query long.
     *
     * @throws GbpRequestFailed
     */
    private function assertEnabled(): void
    {
        if ($this->defaults->value('gbp.zernio_enabled') !== true) {
            throw GbpRequestFailed::disabled();
        }
    }

    /**
     * Both preconditions this client has, in the order an operator would fix
     * them.
     *
     * ⛔ **A SECOND METHOD RATHER THAN A SECOND `if` INSIDE THE ONE ABOVE**
     * (9145). The flag and the key are two states with two remedies — a
     * `platform_settings` row and a paste into Ops → Credentials — and a guard
     * named for one of them that quietly refuses for the other is
     * `CLAUDE.md`'s *guard named for one vendor in an application with two*.
     * Keeping `assertEnabled()` narrow also keeps three test files and
     * `Impersonation.php`, which cite it by name for what it reads, true.
     *
     * ⛔ **THE CREDENTIAL CHECK IS WHY THIS EXISTS, AND IT WAS MISSING
     * ALTOGETHER.** `request()` reads `zernio_api_key` with
     * `PlatformCredentials::get()`, which raises a bare `RuntimeException` — and
     * `Account\Connections` catches `GbpRequestFailed`, `ImpersonationRefused`
     * and `GbpConnectionRefused`, none of which that is. So an unset key was an
     * uncaught throw out of the owner's own Connections screen, on a render that
     * had already resolved their tenant. ⚠️ **That screen's own catch block
     * names this case in prose** — *"the four things that produce one here — our
     * key, their outage, our plan limit, the flag"* — which is a **third**
     * docblock in this tree asserting a `GbpRequestFailed` that the code could
     * not produce, beside the two on `PlatformCredentials::has()` and
     * `CredentialManifest`.
     *
     * ⚠️ **FLAG FIRST, DELIBERATELY.** A switched-off integration with no key
     * pasted is not a credential fault, and reporting it as one sends an
     * operator to paste a key into a vendor nobody has turned on.
     *
     * @throws GbpRequestFailed
     */
    private function assertUsable(): void
    {
        $this->assertEnabled();

        if (! PlatformCredentials::has(self::CREDENTIAL)) {
            throw GbpRequestFailed::unconfigured();
        }
    }

    /**
     * @param  array<string, scalar>  $query
     *
     * @throws GbpRequestFailed
     */
    private function get(string $path, array $query = [], bool $accountScoped = false): Response
    {
        $url = self::BASE.$path;

        try {
            $response = VendorLog::timed(
                'zernio',
                'GET',
                $url,
                fn (): Response => $this->request()->get($url, $query),
            );
        } catch (ConnectionException $e) {
            $failure = self::transportFailure($e);

            VendorLog::failure('zernio', 'GET', $url, $failure->reason);

            throw $failure;
        }

        if ($response->failed()) {
            throw GbpRequestFailed::from($response, $accountScoped);
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::withToken(PlatformCredentials::get(self::CREDENTIAL))
            ->timeout((int) config('gbp.timeout', 10))
            ->acceptJson();
    }

    /**
     * Turn a connection failure into a classified one, in the only place that may.
     *
     * ⛔ **THIS IS THE ONE FACT THAT SEPARATES TWO OPPOSITE OUTCOMES AND IT WAS
     * BEING THROWN AWAY** (7000, closing 6830). Every `catch` in this file read
     * `} catch (ConnectionException) {` — **no variable bound at all** — and
     * threw `unreachable('connection_failed')` for all of them.
     * `ReviewReplies::markPublishUnconfirmed()` wrote
     * that limitation into its own docblock and lived with it, which is why the
     * reply that provably never left was stamped *"we do not know"*, refused on
     * the next attempt, and left for its owner to approve again.
     *
     * ⚠️ **IT WIDENS A SAFE DEFAULT AND NEVER NARROWS ONE, IN EVERY DIRECTION IT
     * CAN FAIL** (7001). Anything this method cannot positively place in
     * {@see self::NEVER_LEFT} keeps the behaviour it had yesterday. A Guzzle
     * upgrade that empties the handler context, a message format that changes,
     * a stream handler with no libcurl code at all, an exception raised by
     * `Http::fake()` with no cause attached — all of them land on
     * `unreachable()`, because {@see self::curlErrno()} answers `null` and
     * `null` is not in the list.
     *
     * ⛔ **A CHOKEPOINT RATHER THAN FOUR CATCH BODIES**, and
     * `Architecture\GbpTest` fails the build on a second construction site or on
     * a `catch (ConnectionException)` in this directory that binds no variable.
     * Four bodies is four chances for the fifth call site to discard the reason
     * again — which is exactly what the four of them did.
     */
    private static function transportFailure(ConnectionException $e): GbpRequestFailed
    {
        return in_array(self::curlErrno($e), self::NEVER_LEFT, true)
            ? GbpRequestFailed::neverSent(self::REASON_NEVER_LEFT)
            : GbpRequestFailed::unreachable(self::REASON_UNKNOWN);
    }

    /**
     * The libcurl code behind a connection failure, or null if it cannot be read.
     *
     * **Two sources, structured first, and the fallback is the one the tests can
     * reach.** Read from the installed framework rather than from memory
     * (`vendor/laravel/framework/src/Illuminate/Http/Client/PendingRequest.php`,
     * laravel/framework in `composer.lock`, 2026-08-21):
     *
     *   1. `marshalConnectionException()` builds
     *      `new ConnectionException($e->getMessage(), 0, $e)` — so the Guzzle
     *      exception is the **previous**, and `ConnectException::getHandlerContext()`
     *      carries `errno` from `CurlFactory::finishError()`'s
     *      `['errno' => $easy->errno, …]`. That is a number, not a sentence,
     *      and it is what production gets.
     *   2. Guzzle's own message is
     *      `sprintf('cURL error %s: %s (%s)', $ctx['errno'], …)`
     *      (`CurlFactory.php`, guzzlehttp/guzzle 7.15.2), so the code is the
     *      first thing in the string. ⚠️ **The pattern is anchored at the start
     *      on purpose**: the same message can end with the request URI, and this
     *      client interpolates a Google review id into that URI. Anchored, no
     *      value a third party controls can reach the capture group; unanchored,
     *      a review id could.
     *
     * ⚠️ **THE FALLBACK IS NOT DEAD CODE AND IS NOT MERELY DEFENSIVE.**
     * `Http::failedConnection()` builds its `ConnectException` with **no handler
     * context at all** (`Illuminate\Http\Client\Factory`), and so does any test
     * that throws one by hand — so in the harness the string is the only source
     * there is. Deleting it would make every never-left assertion in
     * `tests/Feature/Reviews/ReplyTransportReasonTest.php` unreachable while the
     * suite stayed green on the safe arm.
     *
     * ⚠️ **`ConnectException` IS A TRANSITIVE DEPENDENCY** — guzzlehttp/guzzle
     * is not in this application's `composer.json`, it arrives under
     * laravel/framework, and Laravel's own HTTP client type-hints it. If a major
     * version moves the class, the `instanceof` stops matching, source 2 still
     * answers, and if that moved too the answer is `null` and every failure
     * keeps today's behaviour. **There is no version of this going wrong that
     * publishes anything twice.**
     */
    private static function curlErrno(ConnectionException $e): ?int
    {
        $cause = $e->getPrevious();

        if ($cause instanceof ConnectException) {
            $errno = $cause->getHandlerContext()['errno'] ?? null;

            // Positive rather than merely `int`: `finishError()` retries a
            // rewind rather than rejecting when `errno` is zero, so a zero here
            // is a context that was never filled in, not a success.
            if (is_int($errno) && $errno > 0) {
                return $errno;
            }
        }

        return preg_match('/^cURL error (\d+):/', $e->getMessage(), $matches) === 1
            ? (int) $matches[1]
            : null;
    }
}
