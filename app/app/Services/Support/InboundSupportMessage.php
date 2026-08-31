<?php

declare(strict_types=1);

namespace App\Services\Support;

use App\Enums\SupportChannel;
use Carbon\CarbonImmutable;

/**
 * One support message that arrived from outside the application, **already
 * routed to a tenant**.
 *
 * ⛔ **`businessId` IS REQUIRED AND THIS TYPE WILL NOT GUESS IT.** A reply
 * arrives naming no tenant — one support mailbox serves every client, the
 * sender's address may not be one we know, and the subject is whatever their
 * mail client prefixed. `MailReplyRouter` records that exact problem on the mail
 * side and answers it with an eight-character tracking code in `Reply-To`
 * (2097). This type is the far end of that: whoever consumes the transport
 * resolves the tenant *first*, and hands over a message that already knows whose
 * it is. A constructor that accepted an email address and looked a tenant up
 * would put tenant resolution in three places and make the wrong one authoritative.
 *
 * @see SupportInbox for what a consumer is expected to hand over, and for the
 *      two consumers that do not exist yet.
 */
final readonly class InboundSupportMessage
{
    /**
     * @param  int  $businessId  resolved by the transport, never by this type
     * @param  string  $externalRef  the provider's own id for this message, so a
     *                               redelivery cannot append the same words twice
     * @param  ?int  $authorUserId  the account user this came from, when the
     *                              transport could match one — null is ordinary
     *                              and is not an error
     * @param  ?string  $subject  the sender's own subject, when the transport has
     *                            one; SMS has none, which is why it is nullable
     */
    public function __construct(
        public int $businessId,
        public SupportChannel $channel,
        public string $body,
        public string $externalRef,
        public CarbonImmutable $receivedAt,
        public ?int $authorUserId = null,
        public ?string $subject = null,
    ) {}

    /**
     * What to call the thread this opens.
     *
     * A channel-shaped default rather than the first line of the body: a subject
     * built from what somebody wrote is a subject that repeats their words on a
     * list screen, and the body is one click away.
     */
    public function subject(): string
    {
        $subject = trim((string) $this->subject);

        if ($subject !== '') {
            return $subject;
        }

        return match ($this->channel) {
            SupportChannel::Email => 'Email to support',
            SupportChannel::Sms => 'Text to support',
            SupportChannel::App => 'Support request',
        };
    }

    /**
     * Who the audit log records as having done this.
     *
     * `system` when no account user was matched — `AuditService`'s own rule that
     * automation is a first-class actor and a nullable user id would model
     * "nobody did this", which is never true.
     */
    public function actor(): string
    {
        return $this->authorUserId === null ? 'system' : 'user:'.$this->authorUserId;
    }
}
