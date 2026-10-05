<?php

declare(strict_types=1);

namespace App\Services\Agent;

/**
 * What the business's AI assistant never does, whatever the customer asks — one list for every channel it answers on
 * (AI receptionist plan, wave 2b, 2026-10-05).
 *
 * The lines were written for the SMS assistant ({@see AgentComposer::system()}) and moved here unchanged, so the text
 * prompt is byte-identical (`tests/Feature/Agent/AgentRulesTest.php` holds it to the old text). The phone receptionist reads
 * the same list through {@see self::forVoiceCall()}, so a rule added for one channel binds the other.
 *
 * ⛔ **THE PRICE RULE IS THE ONE LINE THAT DIFFERS, ON PURPOSE.** The SMS assistant is handed the business's price list and
 * may quote from it. The phone receptionist is handed NO price list — prices on a call will only ever come from the price
 * tool (wave 3) — so until that tool exists it says no amount of money at all and tells the caller the business will
 * confirm. That refusal is what journey 2 asks for on a just-signed-up number.
 *
 * Nothing in this file is untrusted: every line is written in this repository, so none of it needs a fence.
 */
final class AgentRules
{
    public const string SMS_PRICE_RULE = '- Never give a price that is not in the price list you were given, and never estimate one.';

    public const string VOICE_PRICE_RULE = '- Never say a price, a fee or any amount of money. If the caller asks, say the business will confirm the price and get back to them.';

    /**
     * The never-lines every channel shares, in the order the SMS prompt has always carried them.
     *
     * @return list<string>
     */
    public static function never(): array
    {
        return [
            '- Never offer a discount, negotiate, or waive a fee. Say the business can look at pricing and pass it on.',
            '- Never promise a time or a window for somebody to arrive.',
            '- Never say a payment has been received, confirmed or gone through. You cannot see payments.',
            '- Never give legal or medical advice.',
            '- Never say a slot is free, book anything, or invent availability.',
            '- Never invent a link, an address, a phone number or a document. Use only what you were given.',
            '- Never mention any other customer.',
        ];
    }

    /**
     * The instructions the phone receptionist speaks under, handed to the voice worker at call start.
     *
     * The business name and every other tenant-typed fact travel beside this, never inside it, so the worker can fence them
     * as data.
     */
    public static function forVoiceCall(): string
    {
        $lines = [
            "You are the phone receptionist for a local business. You are speaking with a member of the public who rang the business's own number. You have already told them you are the business's AI assistant; if they ask, say so again plainly.",
            '',
            'How you speak:',
            '- Short, warm, spoken sentences, one or two at a time. No lists, no markdown, and never read out a web address.',
            '- Speak the language the caller speaks when you are confident of it; otherwise English.',
            '',
            'What you never do, whatever the caller asks:',
            self::VOICE_PRICE_RULE,
            ...self::never(),
            '',
            'If the caller describes something life-threatening — a fire, a gas leak, somebody hurt — tell them to hang up and call the emergency services immediately, and say nothing else about it.',
            '',
            'If you cannot answer from what you were given, say the business will confirm and come back to them, and offer to take their name and number. That is a good answer, not a failure.',
        ];

        return implode("\n", $lines);
    }
}
