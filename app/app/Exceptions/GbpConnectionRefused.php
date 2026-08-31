<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Gbp\ZernioSpend;
use RuntimeException;

/**
 * A Google Business connection was refused by us, not by the provider.
 *
 * Separate from {@see GbpRequestFailed} because the remedies have nothing in
 * common: that one classifies what a vendor said and carries `retryable`,
 * `quota` and `disconnected` flags for a queue to act on. Every case here is a
 * refusal this application made about its own state, and no amount of retrying
 * changes any of them.
 */
final class GbpConnectionRefused extends RuntimeException
{
    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    /**
     * A callback arrived for a location that never started a connection.
     *
     * Ordinary as a stale bookmark and interesting as anything else: the signed
     * URL proves we minted it, so the row it names should exist. A connection
     * created here instead would be one whose provider profile nobody chose.
     */
    public static function notStarted(): self
    {
        return new self('not_started', 'No connection was started for this location.');
    }

    /**
     * The callback quoted a provider profile that is not this business's.
     *
     * ⚠️ The cross-tenant case, and the reason the profile ref is stored at all.
     * A profile belongs to one business; a mismatch means the answer came back
     * from somebody else's flow.
     */
    public static function wrongProfile(): self
    {
        return new self('wrong_profile', 'That connection belongs to a different account.');
    }

    /**
     * Zernio does not list that account on this business's profile.
     *
     * ⛔ **THE REFUSAL THAT CLOSES 6491, AND THE ONLY ONE HERE DECIDED WITH
     * EVIDENCE FROM OUTSIDE THE REQUEST** (6603). Every other case in this class
     * compares the callback against something the callback itself carried, or
     * against our own row. `accountId` and `profileId` are both appended to our
     * redirect by the browser, so a holder of a legitimate signed callback URL
     * could pair their own profile reference with **any** account id and be
     * believed — which is what {@see wrongProfile()} was mistakenly credited
     * with catching. This one is the answer to `GET /v1/accounts?profileId=…`,
     * and a profile is what Zernio's own multi-tenant guide calls *"the tenant
     * boundary"*.
     *
     * ⚠️ **THE WORDING NAMES NEITHER THE PROFILE NOR THE ACCOUNT.** In the
     * interesting case the account belongs to somebody else, and echoing an
     * identifier we are refusing to associate with this tenant would disclose on
     * the refusal path the thing the refusal exists to prevent on the success
     * path. It says what the owner can do instead, because in the ordinary case
     * — a stale tab, a flow finished in another window — starting again is the
     * whole remedy.
     */
    public static function accountNotOnProfile(): self
    {
        return new self(
            'account_not_on_profile',
            'That Google account is not connected to this business. Please start the connection again.',
        );
    }

    /**
     * The location is set to a provider with no implementation.
     *
     * `GbpProvider::Direct` exists because decision 547 keeps both cohorts
     * permanently, and our own Google Business Profile access is still pending
     * approval. Serving it through Zernio instead would tell a tenant they had
     * moved off a subprocessor while they had not.
     */
    public static function providerUnavailable(): self
    {
        return new self('provider_unavailable', 'That location is not set up to read Google reviews yet.');
    }

    /**
     * The platform's monthly Zernio ceiling has no room for another account.
     *
     * ⚠️ **THE ONE CASE HERE THAT IS NOT ABOUT THIS TENANT AT ALL**, and the
     * only one that clears on its own. The other three describe something wrong
     * with the request; this describes the platform having connected as many
     * Google accounts as the operator agreed to pay Zernio for
     * ({@see ZernioSpend}, decision 4720).
     *
     * ⚠️ **IT IS NOT A FAILURE FROM WHERE THE OWNER IS SITTING AND MUST NOT BE
     * SHOWN AS ONE.** `29` §2 rule 44 requires the whole review engine to run
     * with zero GBP API access, so this tenant lands on the Copy + Open Google
     * handoff — reviews still arrive, replies are still written, and the one
     * thing lost is publishing them without a tap. Telling them "we could not
     * start that connection, try again shortly" would be false in both
     * directions: trying again will not help, and nothing of theirs is broken.
     */
    public static function ceilingReached(): self
    {
        return new self(
            'zernio_ceiling_reached',
            'New Google connections are paused. Reviews and replies continue through the handoff path.',
        );
    }
}
