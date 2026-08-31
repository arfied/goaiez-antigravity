<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who wrote one message in a thread (DATA-MODEL §5.9).
 *
 * ⛔ **`Assistant` AND `Person` ARE NOT INTERCHANGEABLE AND THE INBOX IS WHY.**
 * T176 §2.3 rail 4 is a claim about *who was speaking to a customer*, answerable
 * years later — so the thread has to record which of the two said each line.
 * A screen that could not tell them apart would let a business owner read the
 * model's sentence as their own, on the one surface where that mistake is
 * repeated back to the customer.
 *
 * ⚠️ **THERE IS NO `System` CASE, DELIBERATELY.** A STOP confirmation, a HELP
 * reply and an opt-in confirmation are compliance artefacts sent by the
 * platform, and they are recorded in `outreach_messages` and the audit log
 * where they belong. Giving them a thread voice would put the platform's own
 * legally-required text into a conversation the tenant is answering, where it
 * reads as something the business chose to say.
 */
enum MessageSenderType: string
{
    /** The person the thread is with. */
    case Customer = 'customer';

    /** A signed-in person at the business. `sender_id` is their user id. */
    case Person = 'person';

    /** `missed_call.sms_agent` took a turn. `sender_id` is the model name. */
    case Assistant = 'assistant';

    /**
     * What the Inbox puts above the message, in outcome language (`22`).
     *
     * ⚠️ **NO NAME AND NO NUMBER.** The label names the role rather than the
     * individual: a thread is read by whoever is at the counter, and which
     * colleague typed a reply is an `audit_log` question rather than a line on
     * a customer conversation.
     */
    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Them',
            self::Person => 'You',
            self::Assistant => 'Your assistant',
        };
    }
}
