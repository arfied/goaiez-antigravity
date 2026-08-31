<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\CanSpamClass;
use App\Services\Assistant\UrgentTerms;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Skill 9's owner ping — one of this business's own urgent words just arrived.
 *
 * ⛔ **THIS IS A PAGE AND {@see AssistantThreadClosed} IS A REPORT, AND THE
 * DIFFERENCE IS THE WHOLE REASON THIS CLASS EXISTS.** An urgent escalation
 * sends both, because `AgentThreadStates::escalate()` fires rail 9's close
 * summary for every escalation and that is correct.
 *
 * ⛔ **THE REASON THIS PARAGRAPH GAVE FOR THE SPLIT WAS A DEFECT IN THE OTHER
 * CLASS, AND IT IS FIXED — CORRECTED 2026-08-22 (7461).** It read: *"rail 9's
 * mail opens 'A conversation finished' and greets the reader with 'One for your
 * records'. Sending only that for a message containing a word the owner typed
 * in under 'the words that mean drop everything' would be a notification that
 * reads as the opposite of urgent."* **That was true of all three of rail 9's
 * statuses, not only the urgent one** — every escalated and every capped thread
 * got a mail titled *"A conversation finished"* about a live conversation
 * waiting on the reader. `AssistantThreadClosed` is status-aware now and
 * subjects a handover *"A conversation needs you"*.
 *
 * ✅ **THE SPLIT SURVIVES ON THE ARGUMENT THAT WAS ALWAYS UNDERNEATH IT**, and
 * it is the section below rather than the subject line: this mail names **which
 * of the tenant's own urgent words matched**, so somebody can decide from the
 * notification itself whether to get up. Rail 9's report cannot carry that and
 * should not — it is sent for endings that have nothing urgent about them.
 * ⚠️ **What is genuinely weaker now is the *volume* case**: two mails whose
 * subjects no longer contradict each other are more obviously two mails about
 * one event, which strengthens 5414's owed merge rather than this class's
 * existence.
 *
 * ⚠️ **SO AN URGENT ESCALATION SENDS THE OWNER TWO EMAILS, AND THAT IS A COST
 * RATHER THAN A DESIGN.** Merging them means threading a reason through
 * `escalate()`, `SummariseClosedThreadJob` and
 * `ThreadCloseSummaries::factualLine()` — three shared classes — on a slice
 * whose subject is that this path did not exist at all. It is recorded as owed
 * rather than done badly here, and the noise is bounded by how rare the event
 * is: a tenant holds at most {@see UrgentTerms::MAX_TERMS} words and chose every
 * one of them.
 *
 * ## ⛔ THE WORDS THAT MATCHED, NEVER THE MESSAGE
 *
 * The matched terms are the **tenant's own vocabulary**, typed into their own
 * assistant screen, and telling an account holder which of their own words fired
 * is what makes the page actionable — *"no heat"* at 2am is a different night
 * from *"invoice"*. **The customer's message is not here and may not be**:
 * `AgentThreadStates::record()`'s rule for the two books applies with more force
 * to a mail, which leaves this system entirely and lands in an inbox no erasure
 * request can reach.
 *
 * ## ⛔ IT ALSO CLAIMED SOMETHING ABOUT THE CUSTOMER THAT HAD NOT HAPPENED YET — 11146
 *
 * ⛔ **THIS MAIL SAID *"THE CUSTOMER HAS BEEN TOLD YOU KNOW ABOUT IT"*, IN THE
 * PAST TENSE, ABOUT AN EVENT SCHEDULED FOR AFTER IT WAS SENT.**
 * `App\Jobs\EscalateUrgentThreadJob::page()` runs **before**
 * `acknowledge()`, deliberately — an owner told only once the customer was
 * successfully texted is an owner told least often exactly when the customer
 * heard nothing — so this notification is already on its way while the
 * customer has been told nothing at all. ⛔ **And often never will be**:
 * `acknowledge()` refuses for a suppressed number, an opted-out contact, and a
 * contact with no consent record, which that method's own docblock calls
 * *"most of them today"*. ⚠️ **The proof was already in the suite**:
 * `UrgentEscalationTest`'s *"an urgent message from a suppressed number pages
 * the owner and texts nobody"* asserts both halves and had been green since
 * the day the two shipped together.
 *
 * ⚠️ **IT IS THE MIRROR OF 11140 AND NEITHER IS FIXABLE BY REORDERING.** That
 * one told a member of the public that the business had been told; this one
 * told the business that the member of the public had been told. The page must
 * go first, so this mail structurally **cannot** know — which means every
 * sentence in it has to be true whatever `acknowledge()` goes on to do, and
 * that is now what it says.
 *
 * ⚠️ **AND THE APPEND-ONLY LOG GETS LESS THAN THIS MAIL DOES**, which looks
 * backwards and is not. `AgentTurns` records the *count* of matched terms and no
 * words at all, because `audit_log` is the store nobody can correct and a matched
 * term is an inference about what a member of the public wrote. A mail to the
 * account holder about their own thread is a correctable, expiring surface; the
 * audit row is neither.
 */
final class UrgentMessageEscalated extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    /**
     * @param  list<string>  $terms  The business's own urgent words the inbound
     *                               message contained, in the order they are
     *                               stored.
     */
    public function __construct(
        private readonly array $terms,
    ) {}

    /**
     * §7702(17)(A)(v) — a report about the recipient's own account, about work
     * their own assistant did for them. `AssistantThreadClosed` carries the
     * argument in full and this is the same claim on a more urgent subject.
     */
    public function canSpamClass(): CanSpamClass
    {
        return CanSpamClass::TransactionalOrRelationship;
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
            ->subject('Something urgent came in')
            ->greeting('This one needs you now')
            ->line('A customer has texted you, and the message contains one of the words you marked as urgent.');

        if ($this->terms !== []) {
            // ⚠️ **THE WORDS, NOT A COUNT.** *"Your urgent words matched"* sends
            // the owner to a screen to find out which; naming them is what lets
            // somebody decide from the notification itself whether to get up.
            $message->line('Your words that matched: '.implode(', ', $this->terms).'.');
        }

        return $message
            ->line('Your assistant has stopped answering this conversation and handed it to you. Open your inbox to read it and reply.')
            // ⛔ **WHAT WE WILL DO, NEVER WHAT WE HAVE DONE — 11146.** This
            // line said *"The customer has been told you know about it, and
            // given your emergency line if you set one."* **It is composed and
            // sent before any of that has happened, and sometimes before none
            // of it ever will.** `EscalateUrgentThreadJob::page()` runs
            // **first**, deliberately and correctly — an owner told only after
            // a successful customer text is an owner told least often exactly
            // when the customer heard nothing — so this mail is on its way
            // while `acknowledge()` has not yet been called. And
            // `acknowledge()` refuses outright for a suppressed number, an
            // opted-out contact or a contact with no consent record at all,
            // which is *"most of them today"*: the file's own test **an urgent
            // message from a suppressed number pages the owner and texts
            // nobody** proves the exact case, and had done since the day both
            // shipped.
            //
            // ⚠️ **IT IS THE MIRROR OF 11140 AND WAS FOUND BESIDE IT.** That
            // one told a member of the public the business had been told; this
            // one told the business the member of the public had been told.
            // **Neither could be fixed by reordering** — the page must go
            // first, so this mail structurally cannot know — so what it says
            // has to be true whatever `acknowledge()` goes on to do.
            //
            // ⚠️ **AND THE SECOND SENTENCE EARNS ITS PLACE.** *"They have heard
            // nothing and are waiting on you"* is the actionable half: an
            // opted-out customer with a gas leak has had no reply at all, and
            // an owner who knows that moves differently.
            ->line('Where we are allowed to text this customer we tell them you have it, and give your '
                .'emergency line if you set one. If they have opted out of our texts, they have heard '
                .'nothing from us and are waiting on you.');
    }

    /**
     * ⛔ **NO MESSAGE TEXT AND NO CUSTOMER, EVER** — `AssistantThreadClosed`'s
     * rule for the same store. This array reaches the database notification
     * store, and the count is enough to say what kind of ping it was.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['terms_matched' => count($this->terms)];
    }
}
