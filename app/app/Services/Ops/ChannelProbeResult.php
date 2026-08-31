<?php

declare(strict_types=1);

namespace App\Services\Ops;

/**
 * What happened when one push channel was asked to carry a test message.
 *
 * ⚠️ **FOUR OUTCOMES AND NOT TWO, BECAUSE THE TWO THAT LOOK ALIKE HAVE
 * DIFFERENT FIXES.** *Not set* is a registry row nobody filled in and is a
 * written decision rather than a fault ({@see OperatorAlerts::EMAIL_KEY}'s
 * blank seed). *Refused* is a channel that is configured and declined before
 * the transport was reached — on SMS that is
 * `PlatformTexter::alertOperator()` returning null because no number in the
 * inventory may originate traffic, which is decision 7252's finding and reads
 * identically to *not set* on every screen this platform has. *Failed* is an
 * exception. *Handed over* is the transport accepting it.
 *
 * ⛔ **AND *FAILED* NOW HAS A CAUSE THAT IS A CONFIGURATION FAULT RATHER THAN A
 * CHANNEL FAULT, WHICH IS WHY IT IS NOT A FIFTH OUTCOME** (11460).
 * `PlatformTexter::alertOperator()` throws
 * `App\Exceptions\TextNotDeliverable::transport()` when the bound SMS driver
 * does not declare `App\Contracts\ReachesRecipients` — the shipped
 * `SMS_DRIVER=log` default is exactly that — and it arrives here as an
 * exception like any other. ⚠️ **A fifth case was considered and refused**: the
 * operator's next move is the same one *failed* already asks for (read the
 * detail, change the thing it names), and the detail on this arm **names the
 * `SMS_DRIVER` value**, which is more use than a second word in the outcome
 * vocabulary. ⛔ **It must not be reported as *refused*** — that word is
 * reserved for a channel that is working and declined, and the two have
 * opposite remedies.
 *
 * ⛔ **HANDED OVER IS NOT DELIVERED AND NOTHING HERE MAY SAY IT IS.** The
 * mailer returning without throwing means SMTP accepted the message; the
 * texter returning a `SentText` means the carrier
 * accepted it. ⛔ **THAT SECOND CLAUSE WAS FALSE ON THE SHIPPED DEFAULT UNTIL
 * 11460 AND IS TRUE NOW BECAUSE OF A GUARD RATHER THAN BECAUSE OF THE
 * RETURN TYPE**: `LogTexter::send()` returns a `SentText` for a message that
 * reaches nobody, so until `alertOperator()` refused that transport this
 * sentence described `infobip` and this probe printed *handed to the transport*
 * on `log`. A bounce, a rejected recipient, a full mailbox and a handset
 * that is switched off all still happen downstream, and this application can
 * observe none of them (open question H). **The only instrument for that half
 * is the operator's own inbox and handset**, which is why the command that
 * renders this says so in as many words.
 */
final readonly class ChannelProbeResult
{
    private function __construct(
        /**
         * `email` or `sms` — the same two words {@see OperatorAlerts::pushChannels()}
         * uses, so one vocabulary describes the channels everywhere.
         */
        public string $channel,
        /**
         * Whether the registry row naming a recipient is filled in.
         */
        public bool $configured,
        /**
         * Whether the transport took the message.
         */
        public bool $handedOver,
        /**
         * The exception class, where one was thrown. **The class and never the
         * message** — see {@see self::$detail}.
         */
        public ?string $failure,
        /**
         * The exception message, with the configured address and number
         * removed.
         *
         * ⚠️ **THE CONSOLE GETS THIS AND THE LOG DOES NOT, WHICH IS A RULE
         * RATHER THAN AN ACCIDENT.** A console line is read by the operator on
         * their own box and thrown away; a log line is stored and drained. The
         * message is where the diagnosis lives — `MailNotDeliverable` alone has
         * five causes and its text names which — so withholding it costs real
         * information, and 7245's *"costs nothing to withhold"* argument does
         * not reach it. ⛔ **The redaction is a mitigation and not a
         * guarantee**: a transport is free to echo a mangled form of the
         * recipient that no `str_replace` can find.
         */
        public ?string $detail,
    ) {}

    public static function notConfigured(string $channel): self
    {
        return new self($channel, configured: false, handedOver: false, failure: null, detail: null);
    }

    public static function handedOver(string $channel): self
    {
        return new self($channel, configured: true, handedOver: true, failure: null, detail: null);
    }

    public static function refused(string $channel): self
    {
        return new self($channel, configured: true, handedOver: false, failure: null, detail: null);
    }

    public static function failed(string $channel, string $failure, ?string $detail): self
    {
        return new self($channel, configured: true, handedOver: false, failure: $failure, detail: $detail);
    }

    /**
     * One phrase, in `22`'s outcome language: what happened, not which class
     * noticed.
     */
    public function outcome(): string
    {
        if (! $this->configured) {
            return 'not set';
        }

        if ($this->failure !== null) {
            return 'FAILED';
        }

        return $this->handedOver
            ? 'handed to the transport'
            : 'REFUSED before the transport';
    }

    /**
     * A configured channel that did not carry the message.
     *
     * ⚠️ **A BLANK ROW IS NOT A FAULT AND MUST NEVER BE COUNTED AS ONE.** Blank
     * is the off switch and it is the only one ({@see OperatorAlerts::email()});
     * an operator who has set one channel and left the other blank has answered
     * the question, and a probe that called that a failure would be a bell with
     * a switch by the back door.
     */
    public function isBroken(): bool
    {
        return $this->configured && ! $this->handedOver;
    }
}
