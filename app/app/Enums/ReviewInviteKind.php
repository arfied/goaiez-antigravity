<?php

declare(strict_types=1);

namespace App\Enums;

use InvalidArgumentException;

/**
 * Which of the two review-invite messages this is — T176 P14.
 *
 * ⚠️ **ONE SENDER, TWO MESSAGES, AND THIS IS WHY THERE IS NOT A SECOND SENDER.**
 * `ReviewInviteSender` already holds the five gates, the containment, the
 * permit, the short-link mint, the credit debit and the cost book. A reminder is
 * the same message with different words three days later, so a second class
 * would be a second copy of every one of those, and this codebase's most
 * repeated lesson is that the copy drifts (`ConsentService::decide()`/`permit()`,
 * `MessageLog::touchesSince()`, `SendCollisionArbiter::classOf()`). What is
 * genuinely different — the arbiter and the legal daytime window — lives in
 * `remind()` and nowhere else.
 *
 * ⚠️ **THE PURPOSE STRINGS ARE `DATA-MODEL` §5.7's OWN VOCABULARY**, verified
 * against the raw artefact rather than invented: `review_request|triage|
 * reminder|owner_alert|fill`. `review_request_reminder` was the obvious
 * invention and would have been a fifth value in a column three other readers
 * already switch on — `MarketingTouch::forOutreachPurpose()` among them, whose
 * `default` arm would have classified it as a low-priority campaign.
 */
enum ReviewInviteKind: string
{
    /** The message that follows a feedback submission within seconds. */
    case Invite = 'invite';

    /** One nudge, at the configured delay, when nothing came of the first. */
    case Reminder = 'reminder';

    /**
     * The value written to `outreach_messages.purpose` (`DATA-MODEL` §5.7).
     *
     * ⚠️ **`MarketingTouch::forOutreachPurpose()` MUST AGREE WITH THIS**, and it
     * does not import this enum on purpose — that method is a `match` over the
     * whole documented vocabulary including values nothing writes, so it is the
     * wider list and this is a subset of it. A test pins the pair.
     */
    public function outreachPurpose(): string
    {
        return match ($this) {
            self::Invite => 'review_request',
            self::Reminder => 'reminder',
        };
    }

    /**
     * The namespace of this kind's provider-cost idempotency key (3730).
     *
     * Distinct per kind because the two messages are two charges: a reminder
     * that reused the invite's namespace would collide with it on the one book
     * whose purpose is reconciliation, and the second charge would go unbooked.
     *
     * ⛔ **THE CHANNEL IS A PARAMETER SINCE 4802, AND IT USED TO BE THE WORD
     * `email` BAKED INTO THE STRING.** That was honest while email was the only
     * channel this class booked a cost for (3730); the review invite's SMS now
     * books one too (2976, closed at 4802), and a key reading
     * `review_invite_email:91:outbound_sms` would have described the wrong
     * product to the one reader this book has. ⚠️ **It is not merely cosmetic**:
     * the two channels are mutually exclusive per review, so a shared prefix
     * would not have collided — it would simply have lied, which is worse on a
     * ledger nobody reconciles against an invoice.
     *
     * ⚠️ **A `match` OVER THE CHANNEL RATHER THAN `$channel->value`**, so the
     * third `OutreachChannel` case — the one this product does not offer — is a
     * compile-time conversation rather than a new namespace appearing in the
     * book unannounced.
     */
    public function costKeyPrefix(OutreachChannel $channel): string
    {
        $suffix = match ($channel) {
            OutreachChannel::Email => 'email',
            OutreachChannel::Sms => 'sms',
            default => throw new InvalidArgumentException(
                'A review invite is only ever sent on email or SMS. A cost key for any other '
                .'channel would namespace a charge this class never made.'
            ),
        };

        return match ($this) {
            self::Invite => 'review_invite_'.$suffix,
            self::Reminder => 'review_invite_reminder_'.$suffix,
        };
    }
}
