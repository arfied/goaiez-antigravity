<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Contracts\Mail\ClassifiesUnderCanSpam;
use App\Enums\AgentThreadStatus;
use App\Enums\AutopilotActionType;
use App\Enums\CanSpamClass;
use App\Services\Agent\ThreadCloseSummary;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Rail 9's owner notify — one line about a conversation that ended (P13).
 *
 * ⛔ **THE COPY NAMES THE SUMMARY AS A SUMMARY, AND THAT IS A REQUIREMENT
 * RATHER THAN A STYLE CHOICE.** What the model wrote is a characterisation of a
 * real conversation and may be wrong. An owner who reads it as a transcript acts
 * on a sentence nobody said — so the mail says whose sentence it is and points
 * at the conversation, which is the record.
 *
 * ⚠️ **AND THE FACTUAL FALLBACK IS LABELLED DIFFERENTLY**, because it is a
 * different kind of claim: *"this conversation ran long"* is a fact about the
 * thread's own state and does not need the hedge that a model's reading does.
 * Wearing the same label would either overstate the fallback or understate the
 * summary.
 *
 * ## ⛔ THE SUBJECT USED TO SAY "A CONVERSATION FINISHED" ON ALL THREE — 7461
 *
 * Rail 9 names three endings and this notification is sent for every one of
 * them, but only the body line was ever status-aware: **the subject said
 * *"A conversation finished"* and the greeting *"One for your records"* about
 * an escalated thread that is live, unanswered, and waiting on the person
 * reading the mail.** Two of the three endings are not endings at all —
 * `Escalated` and `TurnCapped` hand a running conversation to the owner, and
 * `AgentThreadStatus::isReArmable()` says so by answering `true` for both and
 * `false` for `Closed`. So the one line an owner sees in a list of unread mail
 * told them the opposite of what had happened, and the body two lines below it
 * — `ThreadCloseSummaries::factualLine()`, status-aware since the day it
 * landed — disagreed with the subject above it.
 *
 * ⚠️ **THIS IS WHY {@see UrgentMessageEscalated} EXISTS, AND THAT ARGUMENT
 * NARROWS RATHER THAN DISAPPEARS.** That class was written because *"rail 9's
 * mail opens 'A conversation finished' … sending only that for a message
 * containing a word the owner typed under 'the words that mean drop
 * everything' would be a notification that reads as the opposite of urgent."*
 * The subject is fixed here; what still separates the two is that this one is a
 * report and that one is a page — it names the tenant's own matched words, so
 * somebody can decide from the notification itself whether to get up.
 *
 * ## ⚠️ ONE SUBJECT FOR BOTH HANDOVERS, ARGUED RATHER THAN ASSUMED
 *
 * `Escalated` and `TurnCapped` share a subject and a greeting and differ only
 * in the body line, on {@see AutopilotActionType::AssistantAskedYouToTakeOver}'s
 * own test: a second sentence has to distinguish something, and **the owner
 * does the same thing in both** — open the thread and answer it. The distinction
 * that matters is *why* it landed with them, and that is exactly what the body
 * line carries. `AgentThreadStatus`'s *"the owner notification says something
 * different"* is satisfied by the line, which has always differed.
 */
final class AssistantThreadClosed extends Notification implements ClassifiesUnderCanSpam
{
    use Queueable;

    public function __construct(
        private readonly ThreadCloseSummary $summary,
        private readonly AgentThreadStatus $status,
    ) {}

    /**
     * §7702(17)(A)(v) — a report about the recipient's own account, sent because
     * a conversation their own assistant was handling reached an end: it
     * finished, or it was handed back to them. It advertises nothing and is the
     * delivery of the service they bought. `FirstWeekSummary` carries the
     * argument in full.
     *
     * ⚠️ **THE JUSTIFICATION USED TO READ *"because their assistant finished a
     * piece of work for them"*, WHICH WAS TRUE OF ONE OF THE THREE STATUSES
     * THIS IS SENT ON** (7461). The class holds either way — a handover to the
     * account holder about their own live thread is more plainly transactional
     * than a completion report, not less — but a CAN-SPAM justification that
     * describes a third of the mail it classifies is the shape 314–316 is
     * about, and it sat directly above the subject line that made the same
     * mistake.
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
        $waiting = $this->waitsOnOwner();

        $message = (new MailMessage)
            ->subject($waiting ? 'A conversation needs you' : 'A conversation finished')
            ->greeting($waiting ? 'This one is yours now' : 'One for your records');

        if ($this->summary->fromModel) {
            // ⛔ **"YOUR ASSISTANT'S SUMMARY" — NEVER "HERE IS WHAT WAS SAID".**
            $message->line('Your assistant\'s summary of how this one ended:')
                ->line($this->summary->line)
                ->line('That is your assistant\'s reading rather than a record of the conversation — the messages themselves are on the thread.');
        } else {
            $message->line($this->summary->line);
        }

        if ($waiting) {
            // ⛔ **AND WHAT TO DO ABOUT IT, WHICH IS THE OTHER HALF OF 7461.**
            // Telling an owner a conversation is theirs now and stopping there
            // is half a correction: the thread is live and somebody is waiting
            // on the far end of it. The wording is {@see UrgentMessageEscalated}'s
            // verbatim, because that page already established this vocabulary
            // for the same act and two spellings of one instruction is how an
            // owner learns to skim both.
            $message->line('Open your inbox to read it and reply.');
        }

        return $message;
    }

    /**
     * ⛔ **NO MESSAGE TEXT AND NO CUSTOMER, EVER.** This array reaches the
     * database notification store; the summary is prose about a real
     * conversation and the thread is where it belongs.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return ['from_model' => $this->summary->fromModel];
    }

    /**
     * Whether this ending left the conversation with the person reading the mail.
     *
     * ⚠️ **EXHAUSTIVE, AND THE THREE ARMS THAT CANNOT ARRIVE ARE ANSWERED
     * ANYWAY.** `AgentThreadStates` dispatches rail 9's job from exactly three
     * places — `close()`, `escalate()` and the capped arm of `recordTurn()` —
     * so `HumanTakeover`, `Unhandled` and `AgentHandling` never reach here.
     * They are grouped with `Closed` rather than thrown on, because a mail path
     * may not throw (2904) and because the honest answer for all three is the
     * same: whatever else is true of them, none is *the assistant handing this
     * thread to you*. A latch in particular is the owner already answering.
     *
     * ⛔ **IT IS NOT `AgentThreadStatus::isReArmable()`, WHICH GIVES THE SAME
     * ANSWER TODAY BY COINCIDENCE.** That method asks whether a person may hand
     * a thread *back to the assistant*, and it answers `true` for
     * `HumanTakeover` — a thread the owner is already answering, which is
     * exactly the case this must answer `false` for. Reusing it would be right
     * on the three statuses that arrive and wrong on the one that would arrive
     * next, which is the near-miss `ThreadCloseSummaries::factualLine()`
     * records against `ownerLabel()`.
     */
    private function waitsOnOwner(): bool
    {
        return match ($this->status) {
            AgentThreadStatus::Escalated, AgentThreadStatus::TurnCapped => true,
            AgentThreadStatus::Closed,
            AgentThreadStatus::HumanTakeover,
            AgentThreadStatus::Unhandled,
            AgentThreadStatus::AgentHandling => false,
        };
    }
}
