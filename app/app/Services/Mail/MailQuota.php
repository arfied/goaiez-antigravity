<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Console\Commands\PrunePlatformMailSends;
use App\Models\PlatformMailSend;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * How close the sending account is to the ceiling, and what happens before it
 * gets there.
 *
 * ⚠️ **DECISION 2095 IS THAT A METER IS NOT THE MECHANISM.** Email quota architecture asks for
 * the 2,000-a-day Workspace limit to be *"encoded as a visible limit"* with a
 * meter in admin, and that is right as far as it goes — but *"a meter is a thing
 * somebody looks at"*, and this failure is silent and total at once: sends stop,
 * the queue drains normally, every tenant's invites stop landing simultaneously,
 * and the account is locked out **for up to 24 hours** with nothing to do but
 * wait. So there are three mechanisms here and the meter is only the third:
 *
 *   1. **A reserve.** Customer-facing mail is refused before the ceiling, not
 *      at it, so the last slice of the window is kept for sign-in links. An
 *      owner locked out of their account because a review-invite batch ate the
 *      quota is the worst version of this failure.
 *   2. **An alert that fires by itself**, once per window, when the window
 *      crosses the alert ratio — a log line at `critical`, which is what an
 *      operator's monitoring actually watches.
 *   3. **A reading**, for the admin sending screen.
 *
 * ⚠️ **THE WINDOW IS ROLLING 24 HOURS, NOT A CALENDAR DAY**, and this is the
 * vendor fact most likely to be got wrong from memory. Google's own page —
 * `knowledge.workspace.google.com`, *Gmail sending limits in Google Workspace*,
 * read 2026-08-11 — says the limits *"apply over a rolling 24-hour period, not
 * a set time of day"*. A date-keyed counter would permit 2,000 sends at 23:00
 * and 2,000 more at 00:01, which is exactly the pattern that trips a rolling
 * limit, and the counter would read 2,000 while the account was locked.
 *
 * ⚠️ **WHAT THE METER CANNOT SEE, STATED RATHER THAN DISCOVERED.** It counts
 * what *this application* handed to a transport. A message sent from the same
 * Workspace user by a person in the web client, or by another application on
 * the same mailbox, is charged against Google's ceiling and not against this
 * one. That undercount is the reason the alert ratio has headroom and the
 * reserve exists; it is not a reason to trust the number as Google's own.
 */
final class MailQuota
{
    /**
     * Rows per DELETE in {@see self::prune()} — `PrunePublicAudits::CHUNK`'s
     * figure and its reasoning, which every sweep in this family shares.
     */
    private const int PRUNE_CHUNK = 500;

    /**
     * How long the alert stays quiet after firing.
     *
     * ⚠️ **ONE HOUR RATHER THAN ONE WINDOW, AND THE DIFFERENCE MATTERS.** A
     * rolling window has no boundary to reset on, so "once per window" is not
     * expressible — and an alert that fired once and then went quiet for
     * twenty-four hours would be silent through the whole period the operator
     * most needs it. An hour is short enough that the second alert says *this
     * is still happening* and long enough that a busy hour does not produce a
     * line per send.
     */
    public const int ALERT_QUIET_SECONDS = 3600;

    public function alertQuietSeconds(): int
    {
        return $this->defaults->int('mail.quota.alert_quiet_seconds');
    }

    /**
     * The registry key family the ceiling is stored under, one row per mailer.
     *
     * ⛔ **IT WAS ONE KEY FOR TWO TRANSPORTS UNTIL 2026-08-17, AND THE TWO HAVE
     * GENUINELY DIFFERENT LIMITS** (4456, fixed at 4603). Google's Workspace
     * figure is **2,000 per user per rolling 24 hours**; a fresh SES account's
     * is **200 per 24 hours** until production access is granted
     * (`docs.aws.amazon.com/general/latest/gr/ses.html`, §Service quotas, read
     * 2026-08-16 at 4432). One key meant that flipping `MAIL_MAILER=smtp` — the
     * whole of R16's activation — left a **10× over-ceiling** against SES's
     * sandbox quota, with nothing on the transport or in the registry able to
     * say so.
     *
     * ⚠️ **AND THE SPLIT IS BY MAILER RATHER THAN BY TRANSPORT, WHICH IS
     * {@see MailDrivers}'s OWN DISTINCTION.** Two `smtp` mailers can point at
     * two relays with two different limits, and the mailer name is the only
     * thing that tells them apart — the same reason `platform_mail.feedback.*`
     * is keyed this way.
     */
    public const string CEILING_KEY = 'mail.daily_send_ceiling';

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly MailDrivers $drivers,
    ) {}

    /**
     * The registry key holding one mailer's ceiling.
     */
    public static function ceilingKeyFor(string $mailer): string
    {
        return self::CEILING_KEY.'.'.$mailer;
    }

    /**
     * Record that one message was handed to a transport.
     *
     * ⚠️ **CALLED AFTER THE SEND RETURNS, NEVER BEFORE IT.** A message the
     * transport refused was not accepted by the vendor and is not charged
     * against the vendor's ceiling, so counting it first would make the meter
     * drift high on exactly the day something is broken — and a meter that
     * over-reports during an incident is a meter that refuses sends during one.
     */
    public function record(): void
    {
        PlatformMailSend::query()->create([
            'mailer' => $this->drivers->active(),
            'sending_account' => $this->drivers->sendingAccount(),
            'sent_at' => now(),
        ]);

        $this->alertIfNear();
    }

    /**
     * How many messages this account has sent in the last 24 hours.
     */
    public function used(): int
    {
        return PlatformMailSend::query()
            ->where('mailer', $this->drivers->active())
            ->where('sending_account', $this->drivers->sendingAccount())
            ->where('sent_at', '>', now()->subDay())
            ->count();
    }

    /**
     * The registry key this deployment's ceiling is stored under.
     *
     * Named rather than described, because a refusal that cannot say which row
     * to set is a refusal an operator has to come and ask about.
     */
    public function ceilingKey(): string
    {
        return self::ceilingKeyFor($this->drivers->active());
    }

    /**
     * The ceiling this account is held to, or null when nobody has stated one.
     *
     * ⛔ **NULL IS A REAL ANSWER AND IT REFUSES EVERY SEND** (4604). `smtp`
     * carries no seed — see `DefaultsManifest::declaredWithoutSeed()` — because
     * there is no figure this codebase can honestly state for it: the mailer may
     * be SES or any other relay, and even for SES the number is 200 in the
     * sandbox and whatever AWS granted afterwards. The two available wrong
     * answers were both worse than refusing. **Borrowing Google's 2,000** is the
     * defect 4456 recorded: a 10× over-ceiling that reads as a real limit.
     * **Seeding 200** encodes the *sandbox* quota, which is wrong the moment
     * production access lands and wrong in the direction that looks decided.
     *
     * ⚠️ **SO STATING THE CEILING IS PART OF ACTIVATING SES**, which is what
     * 4432 already said should happen and what nothing made true. It is one Ops
     * row, it is named in `.env.example` beside the credentials, and until it is
     * set the queue's mail jobs fail with a message naming the key rather than
     * sending ten times what the account will accept.
     *
     * ⚠️ **A ROW OF `0` FALLS BACK TO THE SEED RATHER THAN REFUSING**, which is
     * `DefaultsRegistry::value()`'s own rule — *an operator who blanked a budget
     * meant to remove a number, not to remove a limit*. On a mailer with no seed
     * that fallback finds nothing, which is where the refusal comes from.
     */
    public function ceiling(): ?int
    {
        if (! $this->drivers->isKnown()) {
            // A transport this application declares nothing about. Refusing is
            // the only honest answer: the alternative is holding an unknown
            // relay to some other vendor's limit.
            return null;
        }

        $key = $this->ceilingKey();

        // `intOr(…, 0)` reads the stored row and nothing else; `seedOf()` is the
        // manifest's answer. Split, rather than `int()`, because `int()` throws
        // on a key with no integer seed and "this mailer has no seed" is exactly
        // the state that has to be answerable.
        $stated = $this->defaults->intOr($key, 0);

        if ($stated > 0) {
            return $stated;
        }

        $seed = $this->defaults->seedOf($key);

        return is_int($seed) && $seed > 0 ? $seed : null;
    }

    /**
     * Whether anything at all may still be sent.
     *
     * ⚠️ **AT THE CEILING THIS REFUSES INSTEAD OF LETTING GOOGLE REFUSE**, and
     * the difference is a day of downtime. Google's answer to the 2,001st
     * message is to stop accepting mail from that user *for up to 24 hours* —
     * so the send that trips the limit costs far more than itself. Refusing here
     * costs one message. ⚠️ **SES's failure differs in kind and not only in
     * size** (4432): an over-rate send is answered with a per-message
     * `Throttling` error rather than a lockout, and the per-second rate that
     * produces it is a limit this key cannot express at all.
     */
    public function hasHeadroom(): bool
    {
        $ceiling = $this->ceiling();

        return $ceiling !== null && $this->used() < $ceiling;
    }

    /**
     * Whether a message to somebody else's customer may still be sent.
     *
     * The reserve. Customer-facing mail stops with a margin left, so that a
     * sign-in link and a support notice still go out on an account that a batch
     * has otherwise consumed. Nothing about this is per tenant: the ceiling is
     * one account's and the reserve protects the platform's own obligations.
     */
    public function hasCustomerHeadroom(): bool
    {
        $ceiling = $this->ceiling();

        return $ceiling !== null && $this->used() < ($ceiling - $this->reserve($ceiling));
    }

    /**
     * What the admin screen shows (email sending quota meter) — `Admin\MailSending`.
     *
     * ⚠️ **THIS HAD NO READER FOR FIVE DAYS AND THE SCREEN IS NOW BUILT** (4442,
     * built at 4600). Until then its only consumers were two exception messages
     * asking for `['used']`, while its own docblock cited *"the admin sending screen"* — a method describing a screen that did not exist, which is
     * 2505's shape at method scope.
     *
     * ⛔ **EVERY FIGURE THE SCREEN RENDERS COMES OUT OF THIS ONE CALL, AND THAT
     * IS 4485's RULE APPLIED BEFORE IT COULD BITE.** There, two panels on one
     * page answered one question two ways because each renderer remembered the
     * rule separately. Here the template does no arithmetic at all: the ratio,
     * the effective reserve and the alert state are computed once, beside the
     * counter and the alert that actually fires.
     *
     * ⚠️ **`reserve` IS THE EFFECTIVE FIGURE AND `reserveConfigured` IS THE ROW**,
     * and both are returned because they can differ. The reserve is clamped
     * below the ceiling, so a deployment with a 200 ceiling and the seeded 200
     * reserve holds back 199 — and the Ops settings screen, which shows the row,
     * would otherwise be one of two screens giving two answers about one
     * deployment. The screen states the derivation instead.
     *
     * ⚠️ **`ceiling` AND `ratio` ARE NULL TOGETHER**, never one without the
     * other: there is no ratio of a ceiling nobody has stated, and rendering a
     * `0%` there would be `RateReading`'s empty-denominator lie in a second
     * place (4484).
     *
     * @return array{account: string, mailer: string, used: int, ceiling: int|null, ceilingKey: string, reserve: int|null, reserveConfigured: int, ratio: float|null, alerting: bool}
     */
    public function reading(): array
    {
        $used = $this->used();
        $ceiling = $this->ceiling();

        return [
            'account' => $this->drivers->sendingAccount(),
            'mailer' => $this->drivers->active(),
            'used' => $used,
            'ceiling' => $ceiling,
            'ceilingKey' => $this->ceilingKey(),
            'reserve' => $ceiling === null ? null : $this->reserve($ceiling),
            'reserveConfigured' => $this->configuredReserve(),
            'ratio' => $ceiling === null ? null : round($used / $ceiling, 4),
            // ⛔ **THE ALERT'S OWN PREDICATE, NOT A SECOND SPELLING OF IT.** This
            // read `round($used / $ceiling, 4) >= $this->alertRatio()` while
            // `alertIfNear()` compared the *unrounded* quotient, so the two
            // disagreed at four decimal places — a screen reporting "alerting"
            // for a window that had raised no alert. 4485's finding, in the one
            // place a reader would take the screen's word for it.
            'alerting' => $ceiling === null ? false : $this->crossedAlertRatio($used, $ceiling),
        ];
    }

    /**
     * Fire the alert if the window has crossed the threshold.
     *
     * ⚠️ **`critical` RATHER THAN `warning`, AND IT NAMES NO RECIPIENT.** The
     * level is what a monitoring rule keys on, and this is the failure that
     * takes every tenant's mail down at once. The line carries the account, the
     * count and the ceiling — an address of ours, never a recipient's, which is
     * the same distinction `platform_mail_sends` draws in its own schema.
     *
     * ⛔ **IT IS DELIBERATELY NOT AN EMAIL.** Alerting about a mail system by
     * sending mail through the mail system that is about to stop accepting mail
     * is the circular dependency this whole class exists to notice, and it would
     * consume one of the messages the reserve is protecting.
     */
    private function alertIfNear(): void
    {
        $used = $this->used();
        $ceiling = $this->ceiling();

        // Nothing to be near. A mailer with no stated ceiling sends nothing at
        // all, so `record()` is not reached on one — and an alert about a
        // limit nobody declared would name no number.
        if ($ceiling === null) {
            return;
        }

        if (! $this->crossedAlertRatio($used, $ceiling)) {
            return;
        }

        $account = $this->drivers->sendingAccount();
        $key = 'mail-quota-alert:'.$this->drivers->active().':'.md5($account);

        // ⚠️ THE CACHE IS THE DEDUPE AND NOTHING ELSE. Losing it costs one extra
        // log line, which is the correct failure for a rate limiter on an alert
        // — the alternative, a table, would make the alert path able to fail on
        // a write and swallow the warning it exists to raise.
        if (! Cache::add($key, true, $this->alertQuietSeconds())) {
            return;
        }

        Log::critical('The platform mail sending account is approaching its 24-hour ceiling.', [
            'mailer' => $this->drivers->active(),
            'sending_account' => $account,
            'used' => $used,
            'ceiling' => $ceiling,
            'reserve' => $this->reserve($ceiling),
            // Named so that whoever reads the alert at 3am does not have to
            // find out from a vendor page what happens next.
            'consequence' => 'At the ceiling this account stops accepting mail for up to 24 hours.',
        ]);
    }

    /**
     * Whether the window has crossed the ratio the alert fires at.
     *
     * ⛔ **ONE PREDICATE, BECAUSE THE SCREEN AND THE ALERT ARE NOT ALLOWED TO
     * DISAGREE** (4485, 4600). {@see self::reading()} compared a *rounded*
     * quotient and {@see self::alertIfNear()} an unrounded one, which differ at
     * the fourth decimal place: a ceiling of 20,000 with 15,999 sent reads as
     * `0.8` rounded and `0.79995` raw, so the meter said the account was
     * alerting about a window that had raised no alert. **Two renderers
     * remembering one rule is what 4485 is about**, and the fix there was the
     * same — compute it once, where the decision is made.
     */
    private function crossedAlertRatio(int $used, int $ceiling): bool
    {
        return $used / $ceiling >= $this->alertRatio();
    }

    /**
     * The reserve as configured, before it is clamped to fit the ceiling.
     */
    private function configuredReserve(): int
    {
        return max(0, $this->defaults->int('mail.ceiling_customer_reserve'));
    }

    /**
     * Delete meter rows past their horizon — decisions 8000-8019.
     *
     * ⛔ **NO OWNER WALK, AND THE ABSENCE IS A DECISION RATHER THAN AN
     * OMISSION.** Pruners in this application walk every user and every
     * business they own, because a range DELETE issued from a console process
     * against a FORCE row-level-secured table matches zero rows and exits 0
     * (7626) — and `SendingHealth::prune()` spent eleven months claiming to
     * step outside that scope while doing exactly nothing (7785(d)).
     * `platform_mail_sends` is `ENABLE`+`FORCE` too, and its policy is
     * `USING (true) WITH CHECK (true)`: it carries no tenant column, because a
     * ceiling on **our own** sending account is not any tenant's fact. So the
     * statement really does match, and a walk here would enumerate every
     * account in order to delete the same rows once.
     *
     * ⛔ **THE PREMISE IS ASSERTED IN A TEST RATHER THAN TRUSTED TO THIS
     * PARAGRAPH.** Narrow that policy one day and this method becomes a sweep
     * that prints a cheerful number and removes nothing, which is
     * indistinguishable from a quiet night. `MailQuotaPruneTest` writes rows,
     * runs the sweep with no tenant established, and counts what is left.
     *
     * ⚠️ **THE HORIZON CANNOT REACH THE CEILING, WHICH IS WHY IT IS SAFE AT ANY
     * VALUE ABOVE A DAY.** {@see self::used()} is the only reader this table has
     * and it counts a rolling twenty-four hours, so no horizon above one day
     * changes what any ceiling, reserve or alert can see. The clamp below is
     * what protects that day — not the number the caller passes.
     *
     * @param  int  $keepDays  {@see PrunePlatformMailSends::RETENTION_DAYS},
     *                         passed by the caller rather than read here, on
     *                         `PlatformHealth::prune()`'s shape
     * @return int rows removed
     */
    public function prune(int $keepDays): int
    {
        // Hoisted out of the loop so a long sweep cannot walk its own boundary
        // forward and leave a tail of rows that were eligible when it started
        // (7890). Clamped at a day for the reason above.
        $cut = now()->subDays(max(1, $keepDays));

        $deleted = 0;

        do {
            // Chunked because the first run of a sweep that arrives after a
            // table has grown is one enormous DELETE, unattended, on the table
            // every outbound message writes to. `PrunePublicAudits::CHUNK`'s
            // figure and its reasoning.
            $batch = PlatformMailSend::query()
                ->where('sent_at', '<', $cut)
                ->limit(self::PRUNE_CHUNK)
                ->delete();

            $deleted += $batch;
        } while ($batch === self::PRUNE_CHUNK);

        return $deleted;
    }

    /**
     * The reserve actually applied against a given ceiling.
     *
     * ⚠️ **THE CEILING IS PASSED IN RATHER THAN RE-READ** (4603). It became
     * nullable when the ceiling went per mailer, and a second read here would
     * be a second answer to *"what is this deployment's ceiling"* — with the
     * clamp arithmetic sitting on top of it.
     */
    private function reserve(int $ceiling): int
    {
        // ⚠️ CLAMPED, because a reserve at or above the ceiling refuses every
        // customer send for ever and reads as a broken feature rather than as
        // the misconfiguration it is. One below the ceiling keeps the refusal
        // honest — there is always at least one message of customer headroom on
        // an unused account.
        //
        // ⚠️ **AND THE CLAMP BITES HARDER SINCE THE CEILING WENT PER MAILER**
        // (4605): the seeded reserve is 200 and an SES sandbox ceiling is 200,
        // so such a deployment holds back 199 and has one message of customer
        // headroom. That is the correct behaviour for a sandbox — which can only
        // reach verified addresses anyway — and it is *stated on the screen*
        // rather than left to be discovered, because a reserve that swallows the
        // whole ceiling reads as a broken feature.
        return min($this->configuredReserve(), $ceiling - 1);
    }

    /**
     * The fraction of the ceiling at which the alert fires.
     *
     * ⚠️ **SEEDED AS AN INTEGER PERCENTAGE AND DIVIDED HERE.** `platform_settings`
     * is jsonb and a hand-edited `0.8` reads back as a float while `.8` does
     * not parse at all — the same trap `DefaultsRegistry::float()` documents.
     * An integer percent has one spelling.
     */
    private function alertRatio(): float
    {
        $percent = $this->defaults->int('mail.ceiling_alert_percent');

        if ($percent <= 0 || $percent > 100) {
            return 0.8;
        }

        return $percent / 100;
    }
}
