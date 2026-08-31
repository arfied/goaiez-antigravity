<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What an owner-directed text was ABOUT — wave 40 lane A, decision 10820.
 *
 * ⛔ **ONE CASE, AND THAT IS THE HONEST COUNT RATHER THAN AN UNFINISHED
 * ENUM.** `App\Services\Sms\PlatformTexter::sendToOwner()` has exactly one
 * caller in `app/` — `App\Jobs\EscalateUrgentThreadJob` — so exactly one kind
 * of owner-directed text has ever left this platform. A case declared for a
 * sender that does not exist is a row nothing can write, which is 272's shape
 * in an enum: every test of the case passes against a value nothing produces.
 * **The second case arrives with the second sender**, in that sender's own
 * slice, and adding one is a single line here plus a description on
 * `App\Models\OwnerNotification`.
 *
 * ⚠️ **THIS IS NOT `AutopilotActionType`, AND IT MUST NOT BECOME A SECOND
 * COPY OF IT.** That enum answers *"what should the owner's activity feed
 * say"* and is rendered; this one answers *"what was the text about"* and is
 * a record. `EscalateUrgentThreadJob::activityAction()` returns null on
 * purpose — `AgentThreadStates::escalate()` already filed
 * `AssistantAskedYouToTakeOver` for this event and *"one event, one row"* is
 * that file's stated rule — so an owner-notification row deliberately writes
 * **no** feed item, and this enum is deliberately not a feed vocabulary.
 *
 * ⛔ **A CASE IS NOT A LICENCE TO SEND.** Adding one here authorises nothing:
 * an owner-directed send still needs an `App\Services\Consent\OwnerSendPermit`,
 * which only `App\Services\Consent\OwnerConsentService` can mint, from a
 * recorded consent event with an un-stopped number (10540, the owner ruling of
 * 2026-08-27).
 */
enum OwnerNotificationKind: string
{
    /**
     * A customer's message matched one of the tenant's own urgent words and
     * the assistant stopped answering — `29` §2.2 row 9, decision 5323.
     *
     * ⚠️ **THE OCCASION FOR THIS KIND IS THE CARRIER'S INBOUND MESSAGE ID**,
     * the same string `App\Jobs\EscalateUrgentThreadJob` keys its run
     * (named in prose and never with a `{@see}`: Pint's
     * `fully_qualified_strict_types` promotes one into a real `use`, and it did
     * exactly that here, leaving an enum importing a job class)
     * and its customer acknowledgement on — so a redelivered webhook and a
     * retried job describe the same occasion rather than two.
     */
    case UrgentEscalation = 'urgent_escalation';

    /**
     * Outcome language, for an operator reading a record of what was sent.
     *
     * ⚠️ **NOT SHOWN TO AN OWNER TODAY AND NOT WRITTEN FOR ONE**, which is
     * unchanged. ⛔ **THE REST OF THIS DOCBLOCK SAID "NO SCREEN RENDERS AN
     * OWNER-NOTIFICATION ROW … THE DAY ONE DOES" AND THAT DAY ARRIVED — wave
     * 41 lane E, decision 11117.** `App\Livewire\Admin\OwnerChannelTexts`
     * renders every row of that table, and this method **had no caller in
     * `app/` or `tests/`** when it did — 272's shape, in the method written to
     * be the answer.
     *
     * ⛔ **THE SENTENCE MOVED HERE RATHER THAN A SECOND ONE BEING WRITTEN ON
     * THE SCREEN.** It read *"Urgent message escalated"*: our own event name,
     * in the passive, saying nothing about who was told — and the screen it now
     * serves exists to answer *"what was said to this person"*. A `match` in a
     * Livewire component would have been **two vocabularies for one enum**,
     * with the uncalled one here going quietly stale, which is the duplication
     * this codebase punishes hardest. ⚠️ **A second case must be given a
     * sentence here**, and the `match` below has no default so it cannot
     * inherit a plausible wrong one.
     */
    public function label(): string
    {
        return match ($this) {
            self::UrgentEscalation => 'We texted them that a customer needed them urgently',
        };
    }
}
