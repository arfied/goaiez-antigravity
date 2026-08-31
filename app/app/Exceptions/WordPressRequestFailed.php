<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\OutboundSiteRefusal;
use App\Enums\WordPressConnectionRefusal;
use App\Services\Actuation\WordPress\WordPressAdapter;
use App\Services\Actuation\WordPress\WordPressCredentials;
use App\Services\Actuation\WordPress\WordPressRestClient;
use App\Support\OutboundSiteBudget;
use RuntimeException;

/**
 * A call to a tenant's WordPress that produced no usable answer.
 *
 * ⛔ **THE MESSAGE IS A FIXED LABEL AND NEVER A VENDOR STRING, AND THAT IS THE
 * WHOLE REASON THIS CLASS EXISTS RATHER THAN A BARE `RuntimeException`.** Three
 * things would otherwise end up in an exception message, a log line and a
 * Sentry-shaped report:
 *
 *   - **the credential**, if the URL were interpolated by anything that had one;
 *   - **the site's own error body**, which is written by whatever plugin refused
 *     and routinely carries a filesystem path, a user login or a SQL fragment;
 *   - **page content**, because a failed write's response body is the page.
 *
 * `VendorLog`'s docblock makes the same argument about payloads and reaches the
 * same conclusion: build the record from an allowlist, never by redacting
 * something you were handed. {@see self::$reason} is that allowlist, one word
 * long.
 *
 * ⚠️ **A refusal is still a return value at the boundary** — `CmsAdapter`'s rule.
 * This is thrown *inside* {@see WordPressRestClient}
 * and caught by {@see WordPressAdapter}, which
 * turns it into an `AdapterOutcome`. Nothing above the adapter ever sees it.
 */
final class WordPressRequestFailed extends RuntimeException
{
    /**
     * ⛔ **THERE IS NO `$retryable` FLAG, AND `GbpRequestFailed` HAS ONE.** It
     * was written and removed inside this slice: **nothing in `app/` would have
     * read it**, because the job that retries a site write is slice D's and does
     * not exist yet — 272's shape, on the exception rather than on a column. The
     * vocabulary a job will branch on is {@see self::$reason}, and its
     * transient labels are `unreachable`, `unavailable` and `throttled` — the
     * last of which carries {@see self::$brake} beside it, because *"we did not
     * ask"* is two different facts and only one of them is about the site.
     *
     * ⚠️ **`WordPressRestClient`'s DOCBLOCK CITED THE FLAG THIS PARAGRAPH SAYS
     * DOES NOT EXIST, FOR AS LONG AS BOTH HAVE BEEN WRITTEN** (6055, corrected
     * 6260). **The paragraph explaining the hazard is what made the sentence
     * beside it read as considered** — 4606's shape — and it took a decision row
     * to notice that one file's bold denial and another file's `{@see}` were
     * about the same absent property.
     */
    private function __construct(
        public readonly string $reason,
        /**
         * Whose brake stopped the request, on the one arm where nothing was
         * sent — `null` on every other.
         *
         * ⛔ **A TYPED PROPERTY RATHER THAN A SECOND SPELLING OF
         * {@see self::$reason}, AND IT IS SET BY EXACTLY ONE CONSTRUCTOR**
         * (9800–9819). The alternative was a compound label —
         * `throttled:host_asked_for_room`, on {@see self::unreadable()}'s
         * precedent — and it fails in the direction this slice exists to close:
         * a caller's `match` on `'throttled'` stops matching, falls to its
         * `default`, and the `default` on both of {@see WordPressCredentials}'
         * arms is a sentence about the owner's website. **A new brake case would
         * have silently become *"we could not find WordPress at that
         * address"***. `reason` therefore stays exactly `'throttled'` — 6262's
         * label, which `WordPressAdapter` renders into an operator's failure
         * line — and the discriminator is carried beside it where a `match` with
         * no `default` can be total over it.
         */
        public readonly ?OutboundSiteRefusal $brake = null,
    ) {
        parent::__construct('wordpress request failed: '.$reason);
    }

    /**
     * Nothing answered — DNS, TLS, a timeout, a refused connection.
     */
    public static function unreachable(): self
    {
        return new self('unreachable');
    }

    /**
     * The credential was refused. Not retryable: the same password comes back.
     */
    public static function unauthenticated(): self
    {
        return new self('unauthenticated');
    }

    /**
     * Authenticated and not permitted — the credential no longer has the
     * capability. Not retryable, and it is what a role changed inside WordPress
     * looks like from here.
     */
    public static function forbidden(): self
    {
        return new self('forbidden');
    }

    /**
     * The page this change set names is not there any more.
     */
    public static function notFound(): self
    {
        return new self('not_found');
    }

    /**
     * The site is up and unwell — a 5xx, or a 429.
     */
    public static function unavailable(): self
    {
        return new self('unavailable');
    }

    /**
     * Nothing was sent, and this is whose brake stopped it.
     *
     * ⛔ **A SEPARATE LABEL FROM `unavailable`, AND THE DISTINCTION IS THE WHOLE
     * POINT OF HAVING IT** (6261). `unavailable` is *"we asked and the server is
     * unwell"*; this is *"we did not ask"* — either because
     * {@see OutboundSiteBudget}'s per-host cap for the window is
     * spent, or because the host's own `Retry-After` is still running. Reusing
     * `unavailable` would file our own politeness as the customer's site being
     * broken, in the audit row an operator reads when a tenant asks why nothing
     * published.
     *
     * ⛔ **AND THE PARAGRAPH ABOVE NAMED TWO CAUSES FROM THE DAY IT WAS WRITTEN
     * WHILE THIS CONSTRUCTOR TOOK NO ARGUMENT — SO EVERY READER BELOW IT SAW
     * ONE** (9754, fixed at 9800–9819). One of the two is the customer's server
     * speaking and the other is ours; by the time
     * {@see WordPressConnectionRefusal::owner()} chose a sentence they were
     * indistinguishable, and the sentence it chose was *"Your website asked us
     * to slow down"* for both. **A docblock that names a distinction the type
     * cannot carry is `CLAUDE.md`'s 314–316**, and it was found by a census
     * quoting this very paragraph back as the evidence. The brake is now a
     * required parameter: a caller cannot throw this without saying whose it
     * was, and cannot forge one it did not get from
     * {@see OutboundSiteBudget::reserve()}.
     */
    public static function throttled(OutboundSiteRefusal $brake): self
    {
        return new self('throttled', $brake);
    }

    /**
     * A `200` this adapter could not make sense of.
     *
     * ⚠️ **`$detail` IS A FIXED LABEL CHOSEN BY THE CALLER, NEVER A VENDOR
     * STRING.** Every call site in `app/` passes a literal, and a test asserts
     * that it does.
     */
    public static function unreadable(string $detail): self
    {
        return new self('unreadable:'.$detail);
    }
}
