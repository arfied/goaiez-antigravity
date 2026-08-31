<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\MailTrackingCode;
use App\Models\OutreachMessage;
use App\Services\Messaging\Outbound\SendSettlement;
use App\Support\Tenancy;
use Illuminate\Mail\SentMessage;
use Illuminate\Support\Facades\DB;

/**
 * The moment an email acquires a handle the vendor will name it by (6361).
 *
 * ⚠️ **THIS IS `SendSettlement` FOR MAIL, AND IT DELIBERATELY DOES NOT REPLACE
 * IT.** The rule that class enforces is not *"SMS counts a send"*, it is **the
 * denominator must have the same membership as the numerator** (3032) — so the
 * counter belongs to the *event* of acquiring a carrier handle, whatever the
 * channel, and there is a lint that fails the build if any other file assigns
 * `provider_msg_id` or calls `recordSent()`. This class produces that event for
 * email and routes it through the same one place.
 *
 * ## Why the handle only exists here
 *
 * ⚠️ **`Notification::send()` RETURNS NOTHING, SO THE TRANSPORT'S ANSWER IS
 * ONLY REACHABLE THROUGH THE EVENT.** `PlatformMailer::deliverNow()` sends
 * through `Notifications::route('mail', …)->notifyNow()`; the `SentMessage`
 * never comes back to it. Laravel fires `MessageSent` with that object, and this
 * is registered on it in `AppServiceProvider` beside the `MessageSending`
 * listener that applies the identity.
 *
 * ⚠️ **WHICH MESSAGE THIS IS COMES FROM `PlatformMailContext`, NOT FROM A NEW
 * HEADER.** The identity is already established around the send — that class's
 * own docblock explains why the delivery is single-threaded and why nesting
 * throws — and it already carries the tenant and the eight-character tracking
 * code, which names the `outreach_messages` row. **A header carrying our own row
 * id would put an internal identifier in every customer's mailbox** to answer a
 * question something in this process already knows.
 *
 * ⚠️ **A MESSAGE SENT OUTSIDE `during()` IS LEFT ALONE**, exactly as
 * `PlatformMailContext::apply()` leaves it alone: platform mail to an account
 * holder has no tenant, no consent record and no outreach row, and inventing one
 * of each in order to count it would put a sign-in link in a tenant's sending
 * rate.
 *
 * ## What the handle actually is on this transport
 *
 * ⚠️ **WE SEND OVER SES's SMTP INTERFACE, NOT ITS API**, so the id is parsed out
 * of the SMTP response rather than read off an API result. AWS documents the
 * successful response as `250 Ok {{MessageID}}`, where *"{{MessageID}} is a
 * unique string of characters that Amazon SES uses to identify a message"*
 * (docs.aws.amazon.com, *Amazon SES SMTP issues*, read 2026-08-20), and the
 * worked example prints
 * `250 Ok 01010160d7de98d8-21e57d9a-JZho-416c-bbe1-8ebaAexample-000000` with the
 * note *"The string of numbers and text that follows `250 Ok` is the message ID
 * of the email"* (*Testing your connection to the Amazon SES SMTP interface
 * using the command line*, same date). Symfony's `SmtpTransport::parseMessageId()`
 * lifts it into `SentMessage::getMessageId()`, and `mail.messageId` on every SES
 * event is *"a unique ID that Amazon SES assigned to the message. Amazon SES
 * returned this value to you when you sent the message"* (*Contents of event data
 * that Amazon SES publishes to Amazon SNS*, same date). **That sentence is the
 * join.**
 *
 * ⛔ **AND ON A TRANSPORT THAT NAMES NOTHING, `getMessageId()` IS THE RFC
 * `Message-ID` HEADER INSTEAD** — Symfony sets it in the constructor and only
 * overwrites it when the MTA response parses. So the value stored is *"whatever
 * this transport calls this message"*, which is exactly what a later feedback
 * event will have to match. It is not asserted to be an SES id, and nothing here
 * validates its shape: a guess at the format would refuse a real id the day AWS
 * changes it, and the consequence of an unmatchable id is an event that finds no
 * row — which {@see MailSendingHealth} already treats as ordinary.
 *
 * ## What this does not do
 *
 * ⛔ **IT DOES NOT DEBIT, METER OR PACE.** `EmailCredits` debits inside the
 * caller's transaction, `MailQuota` records after the send in `deliverNow()`,
 * and `MailSendRate` paces before it. A second counter here would double-count
 * one of them.
 */
final class MailSettlement
{
    public function __construct(
        private readonly PlatformMailContext $context,
        private readonly MailTrackingCodes $codes,
        private readonly SendSettlement $settlement,
    ) {}

    /**
     * Record that the transport accepted this message and named it.
     *
     * ⚠️ **EVERY EARLY RETURN IS AN ORDINARY STATE, NOT A FAILURE.** Platform
     * mail has no identity; a tracked message that is not an outreach message
     * has no row; a transport that answered with nothing has no handle. None of
     * them is worth an exception on a path that runs inside a send that has
     * already succeeded — throwing here would fail a queued job for a message
     * the customer has already received, and the retry would send it again.
     */
    public function record(SentMessage $sent): void
    {
        $identity = $this->context->current();

        if (! $identity instanceof PlatformMailIdentity) {
            return;
        }

        $code = $identity->trackingCode;
        $businessId = $identity->businessId;

        if ($code === null || $businessId === null) {
            return;
        }

        $providerMessageId = trim($sent->getMessageId());

        if ($providerMessageId === '') {
            return;
        }

        Tenancy::actingAs($businessId, function () use ($code, $providerMessageId): void {
            $tracking = $this->codes->resolve($code);

            if (! $tracking instanceof MailTrackingCode) {
                return;
            }

            $outreachMessageId = $tracking->outreach_message_id;

            if ($outreachMessageId === null) {
                return;
            }

            // ⚠️ **TENANT-SCOPED, AND THAT IS WHAT MAKES THE WRITE BELOW SAFE.**
            // `mail_tracking_codes` carries a `USING (true)` policy by
            // necessity, so stamping a row there is unprotected on its own. This
            // read is filtered by the global scope and by FORCE row-level
            // security, so a code belonging to another tenant finds no message
            // and nothing downstream runs. **The order is the guard.**
            $row = OutreachMessage::query()->whereKey($outreachMessageId)->first();

            if (! $row instanceof OutreachMessage) {
                return;
            }

            // ⚠️ **ONE TRANSACTION, BECAUSE THE TWO WRITES ARE ONE FACT.** The
            // outreach row is what a feedback event is counted against and the
            // tracking row is the only way to find it without a tenant; a tree
            // holding one without the other is either a message no event can
            // ever be placed on, or a mapping pointing at a message that does
            // not carry the id. `SendSettlement` moves `sending_health_windows`
            // on the same connection, so a rollback takes the `sent` increment
            // with it — which is the property its own docblock is built on.
            DB::transaction(function () use ($row, $tracking, $providerMessageId): void {
                $this->settlement->settle($row, $providerMessageId, null);

                $this->codes->recordTransportMessageId($tracking, $providerMessageId);
            });
        });
    }
}
