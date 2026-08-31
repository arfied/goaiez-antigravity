<?php

declare(strict_types=1);

namespace App\Services\Mail;

use Closure;
use Illuminate\Mail\Events\MessageSending;
use LogicException;
use Symfony\Component\Mime\Address;

/**
 * Which identity the message currently being delivered is sent under.
 *
 * ⚠️ **A MUTABLE SINGLETON IS A SMELL AND THIS ONE IS ARGUED RATHER THAN
 * EXCUSED.** The alternatives were worse in ways that matter here:
 *
 *   - **Setting `From:` and `Reply-To:` on each notification.** `app/Notifications`
 *     is a growing directory and every future class would have to remember; the
 *     one that forgot would send a customer-facing message with no reply route
 *     and nothing would notice, because a message with no `Reply-To` still
 *     sends. ⚠️ **THE COUNT IS DELETED RATHER THAN CORRECTED — 2026-08-28
 *     (11263).** This read *"nine notification classes"* and the paragraph
 *     below read *"twelve"*, in one file, and there are **26**. **The argument
 *     is that the set grows and each new member is a chance to forget** — a
 *     number is the one thing that cannot say that, and two different numbers
 *     for one set is 8460 inside a single docblock (8861).
 *   - **`Mailer::alwaysFrom()` around the call.** The same global mutation with
 *     no way to restore "no reply-to", performed on a shared framework object
 *     instead of on one of ours.
 *   - **Threading an envelope through `Notification`.** The framework's
 *     signature is not ours to widen.
 *
 * What makes it safe is the shape rather than the intention: `during()`
 * is the only way in, it restores in a `finally`, and it **refuses to nest**.
 * Nesting is the failure that would actually occur — a notification that sends
 * another notification — and it would apply one tenant's name to another
 * tenant's message. It throws instead.
 *
 * ⚠️ **THE DELIVERY IS SYNCHRONOUS AND SINGLE-THREADED, WHICH IS WHY THIS
 * WORKS AT ALL.** `PlatformMailer::deliverNow()` runs inside one queued job,
 * calls `notifyNow()` (never `notify()`), and the `MessageSending` event fires
 * inside that call. A queue worker handles one job at a time; there is no
 * concurrency for this value to be wrong across. If anything ever makes
 * delivery concurrent within one process, this class is the first thing that
 * breaks and it should be deleted rather than made thread-safe.
 */
final class PlatformMailContext
{
    private ?PlatformMailIdentity $current = null;

    /**
     * Run a delivery under this identity.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $deliver
     * @return TReturn
     */
    public function during(PlatformMailIdentity $identity, Closure $deliver): mixed
    {
        if ($this->current !== null) {
            throw new LogicException(
                'A platform mail identity is already established. Nesting one delivery inside '
                .'another would apply the outer message\'s business name and reply code to the '
                .'inner one, which is a message attributed to the wrong tenant.'
            );
        }

        $this->current = $identity;

        try {
            return $deliver();
        } finally {
            // ⚠️ `finally`, so a transport that throws does not leave one
            // tenant's name attached to the next message the worker sends.
            $this->current = null;
        }
    }

    public function current(): ?PlatformMailIdentity
    {
        return $this->current;
    }

    /**
     * Apply the established identity to the message about to leave.
     *
     * Registered on Laravel's `MessageSending` event in `AppServiceProvider`.
     *
     * ⚠️ **A MESSAGE SENT OUTSIDE `during()` IS LEFT ALONE**, not defaulted.
     * `config/mail.php`'s own `from` already answers for platform mail, and
     * inventing an identity here for a message nobody claimed would put a
     * business name on something this class knows nothing about.
     */
    public function apply(MessageSending $event): void
    {
        $identity = $this->current;

        if (! $identity instanceof PlatformMailIdentity) {
            return;
        }

        $address = config('mail.from.address');
        $platformName = config('mail.from.name');

        if (! is_string($address) || trim($address) === '') {
            // `PlatformMailer::assertDeliverable()` has already refused this
            // state, so reaching it means the guard was bypassed. Leaving the
            // message untouched is the only answer that cannot invent a sender.
            return;
        }

        $event->message->from(new Address(
            trim($address),
            $identity->fromName(is_string($platformName) ? $platformName : 'GO AI EZ'),
        ));

        if ($identity->trackingCode !== null) {
            $domain = mb_substr(trim($address), (int) mb_strrpos(trim($address), '@') + 1);

            $event->message->replyTo(
                app(MailTrackingCodes::class)->replyAddress($identity->trackingCode, $domain),
            );
        }

        if ($identity->footer instanceof CanSpamFooter) {
            $this->applyCanSpam($event, $identity->footer);
        }
    }

    /**
     * The RFC 8058 headers and the CAN-SPAM footer, on the one message that
     * owes them — T176 P21.
     *
     * ⚠️ **HERE RATHER THAN IN EACH NOTIFICATION, FOR THIS CLASS'S OWN STATED
     * REASON.** The docblock above already argues it about `From:` and
     * `Reply-To:`: `app/Notifications` grows and every future class would have
     * to remember, and the one that forgot would send a commercial message with
     * no opt-out and nothing would notice, **because a message with no
     * `List-Unsubscribe` still sends**. That is the identical failure shape,
     * with a statutory penalty attached instead of a lost reply.
     *
     * ⚠️ **THE BODIES ARE ALREADY RENDERED WHEN `MessageSending` FIRES, WHICH IS
     * WHY THIS IS STRING WORK AND NOT A VIEW.** The alternative was publishing
     * Laravel's own `mail::message` template into `resources/views/vendor/mail`
     * and adding the block there — a framework file copied into this repository
     * that stops receiving upstream fixes, and that only covers notifications
     * rendered through the markdown pipeline. This covers every message that
     * carries a footer, whatever built it.
     *
     * ⚠️ **BOTH PARTS, AND A MISSING ONE IS SKIPPED RATHER THAN CREATED.**
     * `MailMessage` always produces HTML and text, so both branches run in
     * practice; inventing an absent part would turn a single-part message into
     * a multipart one behind the caller's back.
     */
    private function applyCanSpam(MessageSending $event, CanSpamFooter $footer): void
    {
        // ⛔ **THE TWO HEADERS ARE ONE MECHANISM AND NEITHER WORKS ALONE.** RFC
        // 8058 §3: a client offers the one-click button only when
        // `List-Unsubscribe-Post` is present *and* `List-Unsubscribe` carries an
        // https URI. The value is the exact literal the RFC specifies — a
        // client matches it verbatim, so a reworded one is a header that is
        // present and ignored.
        $headers = $event->message->getHeaders();

        $headers->addTextHeader('List-Unsubscribe', '<'.$footer->unsubscribeUrl.'>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $html = $event->message->getHtmlBody();

        if (is_string($html) && $html !== '') {
            // Before `</body>` where there is one, appended where there is not.
            // ⚠️ The **last** occurrence rather than the first, which is why
            // this is `mb_strripos` and a splice instead of a `str_replace`: a
            // `</body>` quoted inside the message body would otherwise take the
            // footer with it, out of the visible document.
            $close = mb_strripos($html, '</body>');

            $event->message->html($close === false
                ? $html.$footer->asHtml()
                : mb_substr($html, 0, $close).$footer->asHtml().mb_substr($html, $close));
        }

        $text = $event->message->getTextBody();

        if (is_string($text) && $text !== '') {
            $event->message->text(rtrim($text, "\n")."\n".$footer->asText());
        }
    }
}
