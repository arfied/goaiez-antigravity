<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Activity\OwnerDigest;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Here's what we did for you this week" — automation #109's email half
 * (`16-…-CATALOG.md` §12), wave 39 lane C.
 *
 * ⚠️ **CONSTRUCTED FROM SCALARS, NOT FROM `Business` OR `ActivityFeedItem` —
 * `RenewalReminder`'s reason exactly.** `App\Services\Activity\OwnerDigest`
 * reads the feed and hands over strings and counts; this class never becomes
 * a second reader of a table another chokepoint already owns.
 *
 * ⚠️ **PLATFORM MAIL, NOT CUSTOMER MAIL.** The recipient is the account
 * holder and the account relationship is the authorisation — never
 * `sendToCustomer()`, no consent record needed or checked — the same
 * precedent `RenewalReminder` already established for unmetered transactional
 * mail to an account holder.
 *
 * ⛔ **AND IT LEAVES THROUGH `PlatformMailer::deliverNow()` RATHER THAN
 * `send()`, WHICH IS UNUSUAL AND IS ARGUED AT THE CALL SITE** (decision
 * 10852). `SendOwnerWeeklyDigests` stamps a cursor the moment this goes out,
 * and that cursor is not a report on the send — it **is** the window the next
 * digest starts from. `send()` cannot tell its caller whether the message was
 * even queued, so a stamp made on its return closes a week that may never have
 * left the building. Read that command's docblock before moving this back.
 *
 * ⚠️ **NOT `ShouldQueue`, FOR `RenewalReminder`'s REASON, WHICH SURVIVES THE
 * MOVE.** `deliverNow()` calls `notifyNow()` itself; a queueable notification
 * would put this on the queue past the deliverability guards that method just
 * ran.
 */
final class OwnerWeeklyDigest extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * A report on the account's own activity, at the account holder's
     * request (by having an account) — no offer, no incentive, nothing to
     * buy. `RenewalReminder`'s own reasoning: informational content about a
     * subscription the recipient already holds.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
    }

    /**
     * The widest window this class will still call *this week*, in days.
     *
     * ⚠️ **ONE CYCLE PLUS A DAY OF SLACK.** `owners:send-weekly-digest` runs
     * daily and a business is due seven days after its last digest, so an
     * ordinary run's window is seven days — occasionally eight, when the
     * previous day's run was held by `withoutOverlapping` or the schedule
     * slipped. Anything wider than that is a catch-up window and says so.
     */
    private const int ORDINARY_WINDOW_DAYS = 8;

    /**
     * @param  list<string>  $lines  Closed-vocabulary summaries, already
     *                               composed by {@see OwnerDigest}
     *                               — never raw feed content.
     * @param  CarbonInterface  $since  The start of the window these lines were
     *                                  counted over, from
     *                                  {@see OwnerDigest::windowStart()}.
     */
    public function __construct(
        private readonly string $businessName,
        private readonly CarbonInterface $since,
        private readonly array $lines,
        private readonly int $overflow,
        private readonly string $activityUrl,
    ) {}

    /**
     * What to call the window, in the subject line and in the greeting.
     *
     * ⛔ **THE SUBJECT SAID *THIS WEEK* FOR EVERY WINDOW AND THE FIRST WINDOW
     * IS NEVER A WEEK** (decision 10851). `OwnerDigest::windowStart()` opens at
     * the business's own cursor, which is null until the first digest lands —
     * so the first email to every account that predates the feature covered
     * everything back to registration while calling itself a week. Wave 39
     * computed the one value that could have made the sentence honest
     * (`compose()` returns `since`) and nothing read it.
     *
     * ⛔ **BOUNDING THE WINDOW IS NOT ENOUGH ON ITS OWN.** With
     * `OwnerDigest::MAX_WINDOW_DAYS` in place the first window is four weeks
     * rather than a year, and *this week* is still false about it — and it is
     * false about every catch-up window after a pause or an outage too, which
     * is a state this product will keep reaching.
     *
     * ⚠️ **WHAT IT COSTS: THE SENTENCE CHANGES SHAPE BETWEEN SENDS.** An owner
     * who has read four *this week* subject lines gets a *since 18 August* one
     * the week after a pause. That is the honest report of an unusual window,
     * and the alternative on offer was a fixed sentence that is wrong whenever
     * the window is not seven days.
     *
     * ⚠️ **NO YEAR, AND THE CAP IS WHY IT IS SAFE.** A window can never open
     * more than four weeks back, so `j F` cannot be ambiguous across years the
     * way a bare date on an unbounded window would be.
     */
    private function period(): string
    {
        return $this->since->gt(now()->subDays(self::ORDINARY_WINDOW_DAYS))
            ? 'this week'
            : 'since '.$this->since->translatedFormat('j F');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $period = $this->period();

        $message = (new MailMessage)
            ->subject("What we did for {$this->businessName} {$period}")
            ->greeting("Here is what happened {$period}");

        foreach ($this->lines as $line) {
            $message->line($line);
        }

        if ($this->overflow > 0) {
            $message->line("...and {$this->overflow} other ".str('thing')->plural($this->overflow).'.');
        }

        return $message
            ->action('See everything we did', $this->activityUrl)
            ->line('You do not need to do anything — this is just so you can see it working.');
    }
}
