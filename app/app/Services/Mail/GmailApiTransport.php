<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Message;

/**
 * Symfony Mailer's side of the Gmail API send.
 *
 * ⚠️ **A TRANSPORT RATHER THAN A SERVICE CALL, SO THAT NOTHING ABOVE IT
 * CHANGES.** `PlatformMailer`, `DeliverPlatformMail`, every notification and
 * every test that fakes mail all keep working exactly as they did — which is
 * what makes the seam email architecture promises real rather than asserted. Swapping to
 * SES is then `MAIL_MAILER=smtp` and nothing else, because SES is SMTP.
 *
 * ⚠️ **THE MESSAGE IS SERIALISED WITH `toString()`, WHICH IS THE WHOLE RFC 2822
 * MESSAGE INCLUDING HEADERS.** Gmail's `raw` field wants exactly that, and it
 * is also why the on-behalf-of `From:` and the tracking `Reply-To:` applied by
 * `PlatformMailContext` survive: they are set on the Symfony message before
 * it reaches here, so they are in the bytes Google receives rather than
 * arguments to an API that would ignore them.
 *
 * ⚠️ **GMAIL REWRITES THE `From:` HEADER IF IT DOES NOT RECOGNISE IT.** The
 * sending account may only send as itself or as an address it has been
 * configured to send as (a Workspace alias, or a verified "send mail as"
 * identity). This is the operational fact most likely to surprise whoever
 * deploys it: an unrecognised `From:` does not error, it silently becomes the
 * authenticated user's address, and the on-behalf-of display name survives while
 * the address does not. The sending address must therefore be an alias of the
 * `GMAIL_SEND_AS` account on the platform sending domain — `goaieasy.net` since
 * decision 5500, `mail.goaiez.com` under 2114 before it. ⚠️ **That domain is the
 * `mail.sending_domain` seed and not a property of the Workspace account**:
 * `PlatformMailer` refuses any other from address outright, so a send-as alias
 * on a domain the registry does not name never reaches this transport at all.
 */
final class GmailApiTransport extends AbstractTransport
{
    public function __construct(private readonly GmailApiClient $client)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail+api://gmail.googleapis.com';
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        $id = $this->client->send(
            $original instanceof Message ? $original->toString() : $message->toString(),
        );

        // Gmail's own id, so that a message can be traced from a failed job or
        // an NDR back to the send. Symfony calls this the "message id" and it is
        // the transport's handle rather than the RFC `Message-ID` header.
        $message->setMessageId($id);
    }
}
