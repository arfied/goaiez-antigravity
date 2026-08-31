<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Destinations\InviteOption;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The one nudge — T176 P14's follow-up, three days after {@see ReviewInviteEmail}.
 *
 * ⚠️ **A SECOND CLASS RATHER THAN A FLAG ON THE FIRST, AND THE REASON IS THE
 * COPY.** The settled invite's wording is a compliance boundary — `24` §3.3
 * permits a review request *because* it stays strictly transactional — and a
 * `bool $isReminder` threaded through that method would put two sets of words
 * behind one set of tests, where the branch nobody drove is the one that ships
 * an offer. The invite's file is unchanged by this slice, byte for byte.
 *
 * ⚠️ **EVERY CONSTRAINT ON THE INVITE APPLIES HERE UNCHANGED, AND THIS IS THE
 * MESSAGE MOST LIKELY TO ACQUIRE ONE.** No incentive, no offer, no urgency, no
 * scarcity, no second chase. A reminder is where "just a little push" arrives —
 * a discount, a prize draw, *"last chance"* — and every one of those makes this
 * marketing retroactively, for every send, under the Do Not Call registries and
 * rule 7's prior-express-written-consent requirement. `29` §2's first rule bans
 * the incentive outright and is not a matter of classification at all.
 *
 * ⚠️ **IT SAYS WHY THEY ARE HEARING FROM US AGAIN AND THAT IT IS THE LAST
 * TIME.** `22`'s outcome language, and the honest register: a person who has had
 * two messages about one piece of feedback is entitled to know there is no
 * third. The sentence is also true — `MessageLog::reviewInviteReminderSent()` is
 * what makes it so, rather than the copy claiming it.
 *
 * ⚠️ **EVERY LINK IS OUR OWN REDIRECT, NEVER THE PLATFORM'S** — decision 387,
 * and {@see ReviewInviteEmail} carries the full argument. The URLs arrive from
 * `ReviewInvites::offerFor()` already wrapped in a per-send short link by
 * `ReviewInviteSender::tracked()`, which is also what makes the *next* reminder
 * decision able to see whether this one was opened.
 */
final class ReviewInviteReminderEmail extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * @param  list<InviteOption>  $options
     */
    public function __construct(
        private readonly string $businessName,
        private readonly array $options,
    ) {}

    /**
     * Commercial, for {@see ReviewInviteEmail::canSpamClass()}'s reasons and one
     * of its own.
     *
     * ⚠️ **A REMINDER CANNOT BE CLASSED MORE LENIENTLY THAN THE THING IT
     * REMINDS ABOUT.** §7702(2)(A)'s primary-purpose test asks what the message
     * is *for*, and this one is for exactly what the invite was for — asking a
     * stranger to publish a public endorsement. If anything the case is
     * stronger: the first message could be read as closing a transaction the
     * recipient had just completed, and a second, days later, with no new event
     * behind it, cannot.
     *
     * ⛔ **THE CLASS WAS ADDED IN A PARALLEL LANE AND THE LINT IS WHAT CAUGHT
     * IT.** P14 wrote this notification while P21 was writing the contract that
     * requires every notification to answer this question, so it arrived
     * declaring nothing — and `PlatformMailer::deliverNow()` refuses an
     * unclassified notification, which is why seven of P14's own tests errored
     * on the merge rather than one lint failing quietly. That refusal working
     * on the first class it had never seen is the design in
     * {@see ClassifiesUnderCanSpam} doing what its docblock claims.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::Commercial;
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
        $message = (new MailMessage)
            ->subject("A last note about your feedback for {$this->businessName}")
            ->greeting('Thank you again')
            ->line(
                "A few days ago you left {$this->businessName} some feedback, and we sent you a link "
                .'in case you wanted to post it publicly.'
            )
            // ⚠️ The permission to ignore this is in the message itself rather
            // than only in the footer. It is the sentence that keeps a second
            // contact a courtesy instead of a chase.
            ->line('It is still there if you have a moment. If not, nothing more is needed.');

        // ⚠️ One button, exactly as the invite does, and for its reason:
        // `MailMessage` renders a single `->action()` and a second silently
        // replaces the first.
        $primary = $this->options[0] ?? null;

        if ($primary instanceof InviteOption) {
            $message->action($primary->label(), $primary->url);
        }

        foreach (array_slice($this->options, 1) as $option) {
            $message->line("{$option->label()}: {$option->url}");
        }

        return $message->line('This is the last message you will get about it.');
    }
}
