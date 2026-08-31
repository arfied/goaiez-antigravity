<?php

declare(strict_types=1);

namespace Tests\Support;

use Stringable;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * A transport that accepts a message and **names it**, the way SES's SMTP
 * interface does — decision 6367.
 *
 * ⛔ **NEITHER `array` NOR `log` CAN STAND IN HERE, AND THAT IS THE WHOLE
 * REASON THIS EXISTS.** `PlatformMailer::UNDELIVERABLE` lists both by name, so
 * `assertDeliverable()` refuses a send over either and nothing reaches a
 * transport at all — while `mailerCanDeliver()`'s `smtp` opens a socket to
 * `127.0.0.1:2525`, which is a test suite reaching for the network. This is the
 * third option: a transport name Laravel resolves through `Mail::extend()`,
 * which `delivers()` therefore accepts, that sends nowhere.
 *
 * ⚠️ **AND THE NAMING IS THE POINT RATHER THAN A DETAIL.** Laravel's own
 * `ArrayTransport` leaves `SentMessage::getMessageId()` as the RFC `Message-ID`
 * header Symfony generated, because only `SmtpTransport` overwrites it — from
 * the MTA's reply. A test over that transport would prove the plumbing while
 * carrying a value of a completely different shape from the one production will
 * see, and the id is the join key the whole email complaint trip hangs on.
 *
 * ⚠️ **VERIFIED AGAINST THE VENDOR RATHER THAN IMAGINED.** AWS documents the
 * SMTP success response as `250 Ok {{MessageID}}` where *"{{MessageID}} is a
 * unique string of characters that Amazon SES uses to identify a message"*
 * (docs.aws.amazon.com, *Amazon SES SMTP issues*, read 2026-08-20), and
 * `EmailSendingHealthTest` drives Symfony's own `parseMessageId()` over AWS's
 * published example line by reflection — so the value handed out here is the
 * value that method actually returns, rather than one that merely looks like it.
 *
 * ⛔ **IT IS STILL SYNTHETIC AND MUST NOT BE WRITTEN UP AS ANYTHING ELSE.** No
 * real SES event has ever reached this application (open question H); what this
 * class models is the *format* of the handle and the *moment* it arrives.
 */
final class NamingTransport implements Stringable, TransportInterface
{
    /**
     * @var list<SentMessage>
     */
    private array $sent = [];

    /**
     * @param  string  $prefix  the stem of the handle this transport hands back.
     *                          ⚠️ **A PREFIX AND NOT A FIXED ID, BECAUSE THE
     *                          MAILER IS CACHED.** `MailManager` builds one
     *                          mailer per name and keeps it, so every send in a
     *                          test goes through **one** instance of this class
     *                          — and a fixed id would give two messages one
     *                          handle, which the unique index on
     *                          `mail_tracking_codes.provider_msg_id` correctly
     *                          refuses. SES names each message separately and so
     *                          does this. **An empty prefix names nothing**,
     *                          modelling an MTA whose reply does not parse.
     */
    public function __construct(private readonly string $prefix = '') {}

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        $sent = new SentMessage($message, $envelope ?? Envelope::create($message));

        if ($this->prefix !== '') {
            // ⚠️ **THE SAME CALL `SmtpTransport::doSend()` MAKES.** The shape
            // follows AWS's published example — a long stem, then a tail — so
            // that nothing downstream can be passing because the value happened
            // to look like a bare token.
            $sent->setMessageId(sprintf('%s-%06d-000000', $this->prefix, count($this->sent) + 1));
        }

        $this->sent[] = $sent;

        return $sent;
    }

    /**
     * @return list<SentMessage>
     */
    public function sent(): array
    {
        return $this->sent;
    }

    public function __toString(): string
    {
        return 'naming';
    }
}
