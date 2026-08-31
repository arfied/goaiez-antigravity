<?php

declare(strict_types=1);

namespace App\Enums;

use App\Jobs\Reviews\SyncGoogleReviewsJob;
use App\Services\Visibility\VisibilitySyncHistory;

/**
 * Why this platform's most recent look at a location's **Google reviews** did
 * not read any.
 *
 * ⛔ **THE FACT WAS ALREADY BEING RECORDED, PER RUN, PER LOCATION, UNDER THE
 * RIGHT TENANT, AND NOTHING READ IT** (10120–10139).
 * {@see SyncGoogleReviewsJob} has returned
 * `['outcome' => 'unavailable', 'reason' => …]` and
 * `['outcome' => 'handoff', 'reason' => …]` on to `automation_runs` since the
 * day it shipped — the same shape {@see VisibilitySyncHistory} was already
 * parsing for Search Console — while every review surface read its own table,
 * found it empty, and told the owner something about *their customers*.
 *
 * ## ⚠️ Why a sibling enum and not four more cases on `VisibilityAbsenceReason`
 *
 * {@see VisibilityAbsenceReason} is about Search Console: its `sentence()`s all
 * say *"your Google Search data"*, its `state()` returns a
 * {@see VisibilityState}, and four of its cases — `PeriodBeforeFirstRead`,
 * `PropertyForbidden`, `PropertyMissing`, `QuotaExhausted` — are facts about a
 * Search Console **property**, which a review sync has none of. Adding review
 * cases there would put arms nobody can reach into three `match`es on the
 * visibility screen and would give that enum two subjects.
 * {@see CompetitorAbsenceReason} is the precedent: wave 31 shipped **two**
 * absence enums rather than one with a discriminator, and this is the third of
 * the same shape.
 *
 * ## ⛔ What it may never say
 *
 * **That we are reading the listing.** There is no positive case and there must
 * not be one — 9921's rule, and it binds harder here than it did there. This
 * application can prove a sweep ran and a connection exists; it cannot prove
 * Google answered, that the listing is verified, or that one review has ever
 * come back. **A `null` from the reader means only *the last run recorded a
 * read*, never *your reviews are arriving*.**
 *
 * **That a Google review exists or does not.** `29` §2's compliance rules keep
 * first-party reviews and Google reviews in two pipelines, and none of these
 * sentences is about a rating, a customer or a review's content. They are about
 * one thing only: whether **we looked and got an answer**.
 *
 * ## ⚠️ Every case is ours, and that is why the two partition methods are absent
 *
 * `VisibilityAbsenceReason` carries `isThisPlatformsOwnDoing()` and
 * `theOwnerCanAct()` because its population genuinely splits three ways (9825).
 * **This one does not**, and a `match` whose every arm returns the same literal
 * is a question with one answer wearing the costume of a partition — 256's
 * shape. **Both surfaces that render these sentences ask only about a
 * connection that is usable right now**, so the two reasons an owner could act
 * on — not connected, and a revoked grant — are answered by the screen the
 * reader is already standing on, before this enum is consulted at all.
 * ⛔ **A case that has to be filtered out by every caller is a case that should
 * not exist**, which is why `connection_revoked` folds into
 * {@see self::NotConnectedAtLastLook} rather than getting its own arm.
 */
enum ReviewSyncAbsenceReason: string
{
    /**
     * No terminal run of `reviews.google_sync` has ever been recorded for this
     * location.
     *
     * ⚠️ **THE COMMONEST CAUSE IS TWENTY SECONDS OLD AND THE WORST ONE IS
     * PERMANENT.** `GbpConnections::complete()` dispatches a backfill the moment
     * an owner connects, so a freshly connected account is in this state until
     * the queue picks the job up — and an account whose queue is not running is
     * in it for ever, with a bare `0` and nothing beside it. **The same sentence
     * is true and useful in both**, which is what makes the case worth rendering
     * rather than swallowing.
     */
    case NeverRead = 'never_read';

    /**
     * The most recent run ended without reading — the queue's `Failed`, an
     * `Abandoned` worker, or a recorded vendor refusal.
     *
     * ⚠️ **IT CARRIES NOTHING OF THE VENDOR'S WORDS.** `output.reason` holds
     * Zernio's own code, written by somebody else's system, and it does not
     * belong on an owner's screen — `AutopilotJob::failed()` states the rule.
     * ⚠️ **`automation_runs.error` USED TO HOLD THE EXCEPTION MESSAGE AND NO
     * LONGER DOES — 11457.** Since 11330 it holds the class for everything but
     * our own `MailNotDeliverable`. **The conclusion is unchanged**; one of its
     * two premises has stopped being true.
     *
     * ⚠️ **THIS IS ALSO THE FAIL-OPEN LANDING FOR A CODE THIS ENUM HAS NOT
     * HEARD OF**, and the direction is deliberate: `GbpRequestFailed::from()`
     * takes its reason from *Zernio's* `code`, falling back to its `type`,
     * falling back to `'unknown_error'` — **an open vocabulary belonging to a
     * third party**, free to grow without us. An unrecognised code resolving to
     * `null` would mean *we read your reviews*, which is the false absence this
     * whole type exists to close.
     */
    case LastReadFailed = 'last_read_failed';

    /**
     * The most recent run was skipped before it started, or was handed off
     * because reading Google is switched off.
     *
     * ⚠️ **A SKIP IS NOT A FAILURE AND MUST NEVER BE REPORTED AS ONE** —
     * {@see AutomationRunStatus}'s own docblock. `AutopilotJob::handle()` skips
     * on four conditions: the config kill switch, a compliance suspension, the
     * owner's own pause, and the per-automation toggle. **`integration_disabled`
     * joins them from the handoff side** rather than getting a case of its own,
     * because it is the same fact about the same switch: `gbp.zernio_enabled`
     * is what `SyncGoogleReviewsJob::canExecute()` reads and what
     * `Account\Connections` gates its whole screen on.
     */
    case ReadingStopped = 'reading_stopped';

    /**
     * The most recent run found no usable Google connection for this location.
     *
     * ⛔ **THE SENTENCE IS IN THE PAST TENSE AND THAT IS THE WHOLE OF ITS
     * CORRECTNESS.** Both surfaces that render it are only reached for a
     * connection that is usable **now** — Home aggregates over
     * `GbpConnections::usableLocationIds()`, and the Google reviews screen
     * renders it inside its own *Connected* branch — so a present-tense
     * *"Google is not connected"* would be a sentence contradicting the word
     * directly above it. What is true in both worlds is that **the last look
     * found nothing to read through**, which is what it says.
     *
     * ⚠️ **`connection_revoked` FOLDS IN HERE**, from `SyncGoogleReviewsJob`'s
     * `$e->disconnected` arm. A revoked grant is a connection that is not
     * connected; the run row cannot tell the owner anything the connection's own
     * status does not already say better, and that arm calls
     * `GbpConnections::refreshHealth()`, which moves the row to `Disconnected` —
     * a state both surfaces answer for themselves.
     */
    case NotConnectedAtLastLook = 'not_connected';

    /**
     * What the owner reads, in place, wherever an absence is rendered.
     *
     * ⛔ **ONE SENTENCE PER CASE, USED BY EVERY SURFACE, AND IT MAY NOT NAME A
     * COUNT.** It is rendered under a proof number on Home, inside a location
     * card on the Google reviews screen, and under an empty reply queue — so
     * *"this stays at zero"* is unavailable to it for 9922's reason (a tenant who
     * connected, was read and then disconnected has a non-zero count and no
     * reading) and *"for this place"* is unavailable to it because two of the
     * three surfaces are about the account rather than a location.
     *
     * ⚠️ **A `match` WITH NO DEFAULT**, so a fifth case is a fatal in a test
     * rather than a silent fall-through to a vaguer sentence at three call sites.
     *
     * ⚠️ **NO CADENCE IS NAMED.** *"Every fifteen minutes"* is a fact about
     * `routes/console.php`, which is not this file and moves without it.
     */
    public function sentence(): string
    {
        return match ($this) {
            self::NeverRead => 'We have not read your Google listing since it was connected. '
                .'That is on us, not on your listing — the first read happens on its own.',
            self::LastReadFailed => 'Our last read of your Google listing did not finish. '
                .'That is on us, not on your listing — we will try again on our own.',
            self::ReadingStopped => 'Reading your Google listing is paused, so nothing new has been read from it.',
            self::NotConnectedAtLastLook => 'Google was not connected the last time we read your listing, '
                .'so nothing has been read since then.',
        };
    }

    /**
     * A reason code recorded in `automation_runs.output.reason`, or null when it
     * is one this reader does not recognise.
     *
     * ⛔ **AN EXPLICIT `match` RATHER THAN `tryFrom()`, WHICH IS WHERE
     * `VisibilityAbsenceReason` AND THIS PART COMPANY.** That enum's values *are*
     * the strings its writer records, so `tryFrom()` is exact. Here the writer
     * records **five** codes of its own — `location_missing`, `not_connected`,
     * `connection_revoked`, `integration_disabled`,
     * `platform_credential_missing` — plus whatever Zernio puts in `code`, and
     * two of ours resolve to one case. `tryFrom()` would have silently sent
     * `connection_revoked` to the fail-open landing while `not_connected`, one
     * line away in the same job, resolved correctly.
     *
     * ⚠️ **`location_missing` AND `platform_credential_missing` ARE DELIBERATELY
     * NOT LISTED.** Both fall open to {@see self::LastReadFailed}, and folding
     * them in is not laziness: an owner can do nothing about either, and the
     * sentence they would be given is the one they are already given.
     * **`platform_credential_missing` is a real and different fault for an
     * OPERATOR** — a key nobody pasted, as `GbpRequestFailed::unconfigured()`
     * sets out — and this enum has no operator surface; the place that
     * distinction survives is `automation_runs.output.reason` itself, which is
     * untouched.
     *
     * ⚠️ **FAILS OPEN TO NULL RATHER THAN THROWING**, and the caller turns a null
     * into {@see self::LastReadFailed}. The input is a jsonb value written by
     * whatever version of the job was deployed when the row was made, and a page
     * render is not the place to discover that an old row spells something this
     * enum has not heard of.
     */
    public static function fromRecordedCode(mixed $code): ?self
    {
        if (! is_string($code)) {
            return null;
        }

        $map = [
            'not_connected' => self::NotConnectedAtLastLook,
            'connection_revoked' => self::NotConnectedAtLastLook,
            'integration_disabled' => self::ReadingStopped,
        ];

        return $map[$code] ?? null;
    }
}
