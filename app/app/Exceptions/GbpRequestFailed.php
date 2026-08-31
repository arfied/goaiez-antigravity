<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A Google Business Profile read did not succeed.
 *
 * Modelled on {@see ProviderRequestFailed} — same two flags, same refusal to
 * carry a response body — with one addition the intermediary makes necessary.
 *
 * ## Three flags, because there are three different remedies
 *
 * ⚠️ **IT IS FOUR SINCE 7000 AND THIS HEADING SAID THREE.** The fourth is
 * `mayHaveBeenSent` and it is described under its own heading below, because it
 * is not a remedy at all — it is a fact about the *request*, where the three
 * here are facts about the *answer*.
 *
 *   retryable    transport failure, 429, 500, or an upstream 5xx relayed as
 *                502. The queue brings the job back with jittered backoff.
 *
 *   quota        429. A planning signal rather than an outage, exactly as it is
 *                for direct Google access, where 0 QPM is the expected steady
 *                state until an access application clears.
 *
 *   disconnected the tenant's Google connection is gone or unauthorised, not
 *                our credentials. **This is the one that only exists because we
 *                read through an intermediary**, and conflating it with an
 *                authentication failure is the mistake it exists to prevent:
 *                one means "a tenant revoked us and needs asking again", the
 *                other means "our API key is wrong and every tenant is down".
 *                They arrive on the same status code and have nothing else in
 *                common.
 *
 * ## The fourth flag, and why its default is the one that says nothing
 *
 *   mayHaveBeenSent  **`true` means "not positively known to have stayed
 *                    local", never "it was sent"** (7000, 7001). It is the
 *                    default on every constructor, on the parameter itself, so
 *                    a named constructor written next year that does not think
 *                    about this gets today's behaviour rather than a claim.
 *                    Only {@see self::neverSent()} sets it `false`, and only
 *                    `ZernioGbpClient::transportFailure()` may call that, after
 *                    reading a libcurl code that establishes the request never
 *                    left this process. ⚠️ **That class is named in prose
 *                    rather than through a `@see` tag on purpose**: Pint's
 *                    `fully_qualified_strict_types` turns one into a real
 *                    `use` statement, and `Architecture\GbpTest`'s *"nothing
 *                    reaches a Google Business client except through the
 *                    connection store"* reads imports as code and fails the
 *                    build on it. **It did, on the first draft of this
 *                    docblock** — a lint catching a formatter, which is not a
 *                    combination anybody predicts.
 *
 * ## The fifth flag, which is about neither the answer nor the request
 *
 *   clientRefused    **the client declined to run at all** (9145), so there is
 *                    no answer to classify and no request to wonder about.
 *                    Two constructors set it — {@see self::disabled()} and
 *                    {@see self::unconfigured()} — and both mean a person has
 *                    to do something in Ops before any call is possible. It is
 *                    a *fifth* thing rather than a reading of `status === 0`,
 *                    because that would be a proxy and the proxy is wrong:
 *                    {@see self::unreadable()} also carries status `0` and is
 *                    a response the vendor really returned. ⚠️ **A test caught
 *                    exactly that mistake in this slice**, which is why the
 *                    flag exists instead of the shorter condition.
 *
 *   ⛔ Its one job is to stop a client-side refusal being recorded as the
 *   provider's verdict: `ReviewReplies::markDeclinedByProvider()` writes
 *   *"Google would not accept this reply"* into an owner's feed and stamps
 *   `provider_declined_at`, and neither is true of a reply Google has never
 *   seen. Read `mayHaveBeenSent` for a claim about the wire; read this for a
 *   claim about whether anybody at the other end had an opinion.
 *
 * ⛔ **THE ASYMMETRY IS THE WHOLE DESIGN AND MUST SURVIVE ANY EDIT HERE.** A
 * `false` that is wrong publishes a second reply under a tenant's name on a
 * public listing; a `true` that is wrong strands a reply until its owner
 * approves it again, which is recoverable and which somebody can see. So this
 * flag may only ever be *widened* toward `true`, and a change that makes more
 * failures `false` is a change to a safety property (6830).
 *
 * ⚠️ **Zernio does not distinguish them for us.** Its error guide names
 * `invalid_credentials` for "the API key is invalid, revoked, or expired" and
 * offers no distinct code for a dead account connection — it points at the
 * account-health endpoint instead. So `disconnected` is inferred from the
 * account-scoped 401/403 shape and is a best-effort classification, not a
 * reported fact. When it is wrong it is wrong in the safe direction: a genuine
 * key problem reported as a disconnection surfaces as "reconnect Google", which
 * is visible and wrong, rather than as a silent retry loop.
 */
final class GbpRequestFailed extends RuntimeException
{
    private function __construct(
        public readonly int $status,
        public readonly string $reason,
        public readonly bool $retryable,
        public readonly bool $quota,
        public readonly bool $disconnected,
        public readonly bool $mayHaveBeenSent = true,
        public readonly bool $clientRefused = false,
    ) {
        parent::__construct("Google Business read failed ({$status}): {$reason}");
    }

    /**
     * Classify a response the vendor actually returned.
     *
     * The reason comes from Zernio's stable machine-readable `code`, falling
     * back to its `type`, and never from its human-readable `error` string —
     * that one is documented as preserved for backward compatibility, is written
     * for a person, and its wording is free to change under us. Neither the
     * message nor the `platformError` blob is carried: Google's own error bodies
     * echo request parameters and can quote review content, and this message
     * reaches logs and error trackers.
     */
    public static function from(Response $response, bool $accountScoped = false): self
    {
        $status = $response->status();

        $code = $response->json('code');
        $type = $response->json('type');
        $reason = is_string($code) && $code !== ''
            ? $code
            : (is_string($type) && $type !== '' ? $type : 'unknown_error');

        // 429 and 500 are documented retryable. 502 relays an upstream refusal:
        // their guide calls a 5xx upstream transient and a 4xx upstream
        // terminal, so the nested status decides — and when it is absent we
        // treat it as transient, because the alternative is abandoning a review
        // sync on what may have been a blip at Google.
        $retryable = match (true) {
            $status === 429, $status === 500 => true,
            $status === 502 => self::upstreamIsTransient($response),
            $status >= 500 => true,
            default => false,
        };

        // A 401 is always ours: the API key authenticates *us*, and no per-
        // account state can produce one. A 403 is the interesting case, and it
        // takes three conditions rather than one.
        //
        // ⚠️ A 403 IS AMBIGUOUS IN A WAY THAT MATTERS. Zernio documents
        // `permission_error` as "valid key but feature requires plan upgrade" —
        // a billing problem of **ours** — on the same status a revoked tenant
        // connection produces. Classifying every account-scoped 403 as a
        // disconnection sends every owner to re-authorise a connection that is
        // fine, while the actual fix is an invoice nobody is looking at.
        //
        // The discriminator is their `platform` field: an error attributed to
        // Google came back from Google, so it is about that tenant's listing. An
        // error with no platform attribution was raised by Zernio about us. That
        // is the only signal their envelope offers, and it is the reason this
        // classification is documented as best-effort rather than reported.
        $disconnected = $accountScoped
            && in_array($status, [403, 404], true)
            && is_string($response->json('platform'));

        return new self(
            status: $status,
            reason: $reason,
            retryable: $retryable,
            quota: $status === 429,
            disconnected: $disconnected,
        );
    }

    /**
     * A transport failure — nothing was returned to classify.
     *
     * ⚠️ **AND NOTHING HERE SAYS WHETHER THE REQUEST LEFT.** `mayHaveBeenSent`
     * takes its default of `true`, which is this class's way of saying *we do
     * not know*. Every caller that has not read a libcurl code belongs here.
     */
    public static function unreachable(string $reason): self
    {
        return new self(
            status: 0,
            reason: $reason,
            retryable: true,
            quota: false,
            disconnected: false,
        );
    }

    /**
     * A transport failure that provably never left this process.
     *
     * ⛔ **THE ONLY CONSTRUCTOR IN THIS CLASS THAT MAKES A CLAIM ABOUT THE WIRE,
     * AND THE ONLY ONE WHOSE MISUSE PUBLISHES SOMETHING TWICE** (7000). It is
     * {@see self::unreachable()} in every respect but one: status `0`, nothing
     * came back, and the queue may bring the job back — plus the single fact
     * that separates *"we never sent it"* from *"we sent it and did not hear
     * back"* — the distinction `ReviewReplies::markPublishUnconfirmed()` wrote
     * down as discarded and could not make.
     *
     * ⚠️ **ITS ONLY PERMITTED CALLER IS THE CLIENT'S TRANSPORT CLASSIFIER**, and
     * `Architecture\GbpTest` fails the build on a second one. The reason is not
     * tidiness: the claim is only as good as the libcurl code behind it, and a
     * caller that cannot see one has nothing to base it on. A method that
     * *anybody* may call is a method somebody calls because the name reads
     * right.
     *
     * ⚠️ **The two codes it is built for are 6 and 7** — `CURLE_COULDNT_RESOLVE_HOST`
     * and `CURLE_COULDNT_CONNECT`. Which codes qualify is the client's to argue
     * and is argued there, next to the parsing that reads them.
     */
    public static function neverSent(string $reason): self
    {
        return new self(
            status: 0,
            reason: $reason,
            retryable: true,
            quota: false,
            disconnected: false,
            mayHaveBeenSent: false,
        );
    }

    /**
     * A create is still in flight under the same idempotency key.
     *
     * Zernio returns 409 both for "a profile with this name already exists" and
     * for "a request with the same Idempotency-Key is still processing", and
     * only the first carries `details.existingProfileId`. The second is a race
     * with ourselves and clears on its own, so it is **retryable** — which
     * {@see self::from()} would never say of a 409, that method reading a 409 as
     * an ordinary terminal 4xx.
     */
    public static function stillProcessing(): self
    {
        return new self(
            status: 409,
            reason: 'idempotency_key_in_flight',
            retryable: true,
            quota: false,
            disconnected: false,
        );
    }

    /**
     * The call succeeded and the answer could not be read.
     *
     * Its own outcome rather than a success with a null in it. `GbpReviewPage`
     * takes the opposite view for a *page* of reviews — an unreadable list is an
     * empty last page, because abandoning a sync over a renamed wrapper key
     * loses every review behind it. The difference is what the value is for: a
     * missing profile id or auth URL is not a smaller answer, it is nothing to
     * store and nowhere to send somebody.
     *
     * Not retryable: the same request returns the same unreadable shape, and a
     * backoff ladder would only delay the moment a person looks at it.
     */
    public static function unreadable(string $reason): self
    {
        return new self(
            status: 0,
            reason: $reason,
            retryable: false,
            quota: false,
            disconnected: false,
        );
    }

    /**
     * The integration is switched off.
     *
     * Not retryable and not a vendor failure: a caller that reaches a disabled
     * client has a scheduling bug, and retrying it would hide that behind a
     * backoff ladder.
     */
    public static function disabled(): self
    {
        return new self(
            status: 0,
            reason: 'integration_disabled',
            retryable: false,
            quota: false,
            disconnected: false,
            clientRefused: true,
        );
    }

    /**
     * The integration is switched on and has no platform API key.
     *
     * ⛔ **A SIBLING OF {@see self::disabled()} RATHER THAN A KIND OF IT**
     * (9145). Both mean *this client cannot run*, and an operator has to be able
     * to tell them apart from a log line, because the two remedies are opposite:
     * one is a `platform_settings` row somebody meant to leave off, the other is
     * a credential nobody pasted. Folding them into one reason would put the
     * likelier fault behind the sentence *"a caller that reaches a disabled
     * client has a scheduling bug"*, which is exactly the wrong place to look.
     *
     * ⚠️ **NOT RETRYABLE, AND THAT IS THE HONEST HALF.** A backoff ladder
     * cannot paste a key. `SyncGoogleReviewsJob` and `PostReplyJob` both read
     * `retryable`, so saying `true` here would spend a job's whole retry budget
     * on a state only a person can clear.
     *
     * ⚠️ **`disconnected: false` IS LOAD-BEARING.** `GbpConnections` reads that
     * flag to decide whether a tenant's Google authorisation has gone; our own
     * missing key is not their listing being disconnected, and reporting it as
     * one sends an owner to reconnect something that is not broken —
     * `CredentialManifest`'s entry for this key already says so.
     */
    public static function unconfigured(): self
    {
        return new self(
            status: 0,
            reason: 'platform_credential_missing',
            retryable: false,
            quota: false,
            disconnected: false,
            clientRefused: true,
        );
    }

    /**
     * Whether a 502's nested upstream status should be treated as transient.
     */
    private static function upstreamIsTransient(Response $response): bool
    {
        $upstream = $response->json('platformError.status');

        return ! is_int($upstream) || $upstream >= 500;
    }
}
