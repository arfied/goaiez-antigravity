<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Services\Config\DefaultsRegistry;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Sleep;

/**
 * The per-second sending rate — 4432's second limit, named for a day and built
 * at 4648.
 *
 * ## What the vendor actually says
 *
 * ⚠️ **EVERY FIGURE HERE WAS FETCHED FROM AWS ON 2026-08-17 AND NONE WAS
 * RECALLED.** `docs.aws.amazon.com/general/latest/gr/ses.html`, §Service quotas,
 * gives a fresh account two adjustable limits per Region: **Sending quota —
 * "Each supported Region: 200 per 24 hours"**, and **Sending rate — "Each
 * supported Region: 1 per second"**, described as *"The maximum number of emails
 * that Amazon SES can accept each second for this account in the current
 * Region."* {@see MailQuota} is the first of those. This is the second, and the
 * ceiling family under {@see MailQuota::CEILING_KEY} cannot express it: a
 * 24-hour ceiling and a per-second rate are not the same shape of limit and do
 * not fail the same way.
 *
 * ⚠️ **THAT KEY IS NAMED THROUGH `MailQuota` AND IS DELIBERATELY NOT SPELT OUT
 * HERE.** `MailTest`'s *"the sending ceiling is read through MailQuota and
 * nowhere else"* fails the build on the literal anywhere in `app/`, docblocks
 * included — and it caught this class's first draft, which is the lint working
 * rather than an inconvenience. Its rule is 4456's: a second place naming that
 * key is a second answer to *"which row governs this deployment"*.
 *
 * ## Why this fails OPEN where the ceiling fails CLOSED
 *
 * ⛔ **THE OPPOSITE DIRECTION FROM `MailQuota`, AND THE REASON IS THE VENDOR'S
 * FAILURE MODE RATHER THAN SYMMETRY.** An unstated *ceiling* refuses every send
 * (4604), because exceeding a 24-hour quota is answered by Google with a
 * lockout of up to a day and by SES with `454 Throttling failure: Daily message
 * quota exceeded` — the send that trips it costs far more than itself. An
 * unstated *rate* throttles nothing, because AWS answers an over-rate send with
 * a **per-message** `454 Throttling failure: Maximum sending rate exceeded`
 * (`docs.aws.amazon.com/ses/latest/dg/troubleshoot-smtp.html`, read 2026-08-17)
 * — a 4xx, which is transient by definition and which the queue's retry already
 * absorbs. **Failing closed on a rate would refuse all mail to avoid a
 * recoverable per-message error**, which is a cure worse than the disease and
 * is exactly the "never hard-fail" half of rule 43 that survived 3293.
 *
 * ## It paces; it does not promise
 *
 * ⛔ **THIS IS NOT A GUARANTEE THAT NOTHING IS EVER THROTTLED, AND WRITING IT UP
 * AS ONE WOULD BE 314–316 IN THE SLICE THAT BUILT IT.** AWS's own page says
 * both halves out loud
 * (`docs.aws.amazon.com/ses/latest/dg/manage-sending-quotas.html`, read
 * 2026-08-17): *"You can exceed this quota for short bursts, but not for
 * sustained periods of time"*, and — the sentence that settles it — *"The rate
 * at which Amazon SES accepts your messages can be less than the maximum send
 * rate for your account."* So the vendor is more permissive than a strict gate
 * in one direction and less predictable than one in the other. **The retry is
 * still what absorbs a throttle**; this only stops a drained queue presenting a
 * sustained over-rate, which is the shape AWS says it will not tolerate.
 *
 * ⚠️ **AND IT WAITS RATHER THAN RELEASING, WHICH IS A CORRECTNESS DECISION AND
 * NOT A PREFERENCE** (4649). `$job->release()` is the idiomatic queue throttle
 * and it **silently drops the message under `QUEUE_CONNECTION=sync`**:
 * `SyncJob::release()` marks the job released and there is no queue to put it
 * back on. `sync` is Laravel's own default, is what this test suite runs
 * (`phpunit.xml.dist`), and is a legitimate small-deployment choice — so a
 * release-based throttle would lose real mail on some deployments while every
 * test stayed green, which is the "never silently drop a message" clause of the
 * same rule. Waiting is correct on every driver.
 *
 * ⚠️ **THE WAIT IS BOUNDED AND THEN THE MESSAGE GOES ANYWAY.** Past
 * {@see self::MAX_WAIT_SECONDS} this hands the message over and lets the 454 and
 * the queue's backoff take it, which is AWS's own documented handling —
 * *"implement a system that retries requests with progressively longer wait
 * times (for example, wait 5 seconds before retrying, then wait 10 seconds, and
 * then wait 30 seconds)"* — rather than an invention of ours. A pacer that
 * blocked a worker indefinitely would be a hard-fail wearing a delay.
 *
 * ## Scope, and what it cannot coordinate
 *
 * ⚠️ **THE COUNTER IS ONLY AS SHARED AS THE CACHE STORE.** Two workers pace each
 * other on `database`, `redis` or `file`, and not on `array` — which is the test
 * suite's store and the right answer there. Nothing in this application chooses
 * that store, and a deployment on a per-process cache paces per process, which
 * degrades toward the unthrottled behaviour rather than toward a wrong one.
 */
final class MailSendRate
{
    /**
     * The registry key family, one row per mailer.
     *
     * ⚠️ **PER MAILER AND NOT PER TRANSPORT, WHICH IS {@see MailDrivers}'s OWN
     * DISTINCTION** and 4603's argument for the ceiling repeated: two `smtp`
     * mailers can point at two relays with two different rates, and the mailer
     * name is the only thing that tells them apart.
     */
    public const string RATE_KEY = 'mail.send_rate_per_second';

    /**
     * How long one send may be held back before it is handed over regardless.
     *
     * ⚠️ **AWS'S OWN FIRST RETRY INTERVAL, RATHER THAN A NUMBER CHOSEN HERE.**
     * `troubleshoot-smtp.html` (read 2026-08-17) prescribes *"wait 5 seconds
     * before retrying"* for a 4xx. Beyond that the honest thing is to stop
     * holding a worker and let the documented path run.
     */
    private const int MAX_WAIT_SECONDS = 5;

    /**
     * The limiter's window.
     *
     * A per-second rate is a one-second window by definition, and it also caps
     * a single wait at one second — so the loop below can only iterate, never
     * hang.
     */
    private const int WINDOW_SECONDS = 1;

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly MailDrivers $drivers,
        private readonly RateLimiter $limiter,
    ) {}

    /**
     * The registry key holding one mailer's rate.
     */
    public static function rateKeyFor(string $mailer): string
    {
        return self::RATE_KEY.'.'.$mailer;
    }

    /**
     * The registry key this deployment's rate is stored under.
     *
     * Named rather than described, for {@see MailQuota::ceilingKey()}'s reason:
     * a screen that cannot say which row to set is one somebody has to come and
     * ask about.
     */
    public function rateKey(): string
    {
        return self::rateKeyFor($this->drivers->active());
    }

    /**
     * How many messages a second this account may hand over, or null for
     * *nobody has stated one*.
     *
     * ⚠️ **NULL MEANS NO PACING AND IS THE STATE OF EVERY DEPLOYMENT TODAY.**
     * No mailer carries a seed: SES's `1 per second` is the **sandbox** figure
     * and is wrong the moment production access lands, an `smtp` mailer need not
     * be SES at all, Google publishes no per-second rate for Workspace, and
     * `log` and `array` hand nothing to anybody. Seeding any of them would be
     * the 10× defect of 4456 with a different unit. See the class docblock for
     * why the empty state is *open* here and *closed* on the ceiling.
     */
    public function perSecond(): ?int
    {
        if (! $this->drivers->isKnown()) {
            // A transport this application declares nothing about. Holding an
            // unknown relay to some other vendor's rate would be a guess, and
            // the guess costs latency on every message.
            return null;
        }

        $key = $this->rateKey();

        // `intOr(…, 0)` reads the stored row and nothing else; `seedOf()` is the
        // manifest's answer. Split rather than `int()`, which throws on a key
        // with no integer seed — and "this mailer has no seed" is the ordinary
        // case here rather than an error. `MailQuota::ceiling()` reads its own
        // key the same way, including the rule that a row blanked to `0` falls
        // back to the seed.
        $stated = $this->defaults->intOr($key, 0);

        if ($stated > 0) {
            return $stated;
        }

        $seed = $this->defaults->seedOf($key);

        return is_int($seed) && $seed > 0 ? $seed : null;
    }

    /**
     * Hold this send back until the account's rate allows it, then let it go.
     *
     * ⛔ **IT NEVER THROWS, NEVER REFUSES AND NEVER DROPS.** The only outcomes
     * are *proceed now* and *proceed shortly*, which is what makes it safe to
     * sit on the one choke point every outbound message passes through. A
     * refusal here would be a hard-fail on a limit whose real consequence is a
     * transient 454.
     *
     * ⚠️ **THE SLOT IS TAKEN WHETHER OR NOT THE WAIT SUCCEEDED**, because the
     * message is handed over either way and a counter that skipped those would
     * under-report exactly during the burst it exists to smooth — the inverse of
     * {@see MailQuota::record()}'s "never count what the transport refused", and
     * for the same reason: the count has to match what the vendor saw.
     */
    public function pace(): void
    {
        $limit = $this->perSecond();

        if ($limit === null) {
            return;
        }

        $key = $this->limiterKey();
        $waited = 0;

        while ($waited < self::MAX_WAIT_SECONDS && $this->limiter->tooManyAttempts($key, $limit)) {
            // ⚠️ **CLAMPED UP TO ONE SECOND, NOT DOWN.** `availableIn()` can
            // answer `0` for a window about to turn over, and sleeping zero in a
            // loop is a busy-wait on a queue worker. The window is one second,
            // so this can never sleep longer than that in one pass.
            $wait = max(1, $this->limiter->availableIn($key));

            Sleep::for($wait)->seconds();

            $waited += $wait;
        }

        $this->limiter->hit($key, self::WINDOW_SECONDS);
    }

    /**
     * The cache key the window is counted under.
     *
     * ⚠️ **MAILER *AND* SENDING ACCOUNT, WHICH IS THE GRANULARITY THE VENDOR
     * METERS AT** — SES's rate belongs to an AWS account in a Region, not to a
     * config block — and it is the same pair {@see MailQuota::used()} filters
     * its window by. ⚠️ **The address in it is ours and never a recipient's**,
     * which is the line `platform_mail_sends` already draws in its own schema.
     */
    private function limiterKey(): string
    {
        return 'mail-send-rate:'.$this->drivers->active().':'.$this->drivers->sendingAccount();
    }
}
