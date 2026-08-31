<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The way back from an SES event to the tenant that sent the message (6360).
 *
 * ⛔ **`sending_health_windows` HELD NO EMAIL ROW AT ALL AND COULD NOT**, so
 * `SendingGuard::shouldTrip(OutreachChannel::Email)` read an empty window, found
 * no volume and could never fire — while 2101/2102/2113 make an automatic
 * complaint trip a precondition of sending. That is 2496's defect exactly, on
 * the channel R16 made primary: a threshold set on a dead counter.
 *
 * ⚠️ **THE MISSING PIECE WAS NEVER THE COUNTER, IT WAS THE JOIN.** SMS carries
 * the business id out to Infobip as `callbackData` and reads it home again, so
 * `DeliveryReceipts` can `Tenancy::actingAs()` before it looks anything up.
 * A bounce, a complaint or a delivery from SES names a recipient, an SES message
 * id and **nothing that names a business** — and `outreach_messages` is FORCE
 * row-level security keyed on `business_id`, so a lookup with no tenant
 * established returns nothing whatever query you write. **The tenant has to be
 * established before the row can be found, which means something un-tenanted has
 * to hold the mapping.**
 *
 * ## Why `mail_tracking_codes` and not a new table
 *
 * That table already *is* the un-tenanted resolver for this channel — its own
 * docblock: *"it is what establishes the tenant for a request that has none"* —
 * and it already carries `business_id` **and** `outreach_message_id`, written at
 * the moment of the send it belongs to. A reply comes back on the eight
 * characters; a feedback event comes back on the SES message id. **Two handles
 * on one row, both minted by one send, resolved by one class.** A second table
 * would duplicate the row's lifetime, its tenancy argument and its RLS posture
 * in order to hold one string.
 *
 * ⚠️ **IT STAYS FREE OF PERSONAL DATA, WHICH IS THAT TABLE'S OTHER RULE.** An
 * SES message id is the vendor's opaque handle for a message. It is not an
 * address, a subject or a body, and pairing it with a business id says no more
 * than the code already sitting beside it.
 *
 * ## The two timestamps, and why the status column could not do this job
 *
 * ⛔ **`OutreachStatus::Delivered` IS TERMINAL AND SETTING IT FROM A MAIL EVENT
 * WOULD SILENTLY DROP EVERY EMAIL REPLY.** `MailReplyRouter`'s docblock has said
 * so since 2097 and predicted this exact slice: *"if SES delivery events are
 * ever ingested, a reply to a delivered message would be silently dropped here"*.
 * So delivery is recorded as a timestamp beside the status rather than in it.
 *
 * ⚠️ **AND THE TIMESTAMPS ARE THE IDEMPOTENCY GUARD, NOT DECORATION.** SNS
 * delivers at least once and AWS makes no ordering or batching guarantee
 * (*"SES does not make ordering or batching guarantees for notifications sent
 * through Amazon SNS"* — docs.aws.amazon.com, *Amazon SNS notification contents
 * for Amazon SES*, read 2026-08-20). A redelivered `Delivery` would increment
 * the **denominator** of the complaint rate a second time, which pushes the rate
 * **down** and suppresses the trip; a redelivered `Complaint` would inflate the
 * numerator. `MailSendingHealth` writes each column with `whereNull(...)` and
 * counts only when the update affected a row, so the counter moves once per
 * message per outcome — which is what `DeliveryReceipts` gets for free from the
 * one-way status machine and email cannot.
 *
 * ⚠️ **BOTH OUTCOMES CAN GENUINELY HAPPEN TO ONE MESSAGE**, which is why they
 * are two columns and not one. AWS again: *"the receiving mail server might
 * accept the email (triggering a delivery notification), but after processing
 * the email, the receiving mail server might determine that the email actually
 * results in a bounce"*.
 *
 * ⚠️ **NO NEW RLS STATEMENTS.** `outreach_messages` is already `ENABLE` +
 * `FORCE` with a tenant policy and `mail_tracking_codes` is already `ENABLE` +
 * `FORCE` with the deliberate `USING (true)` its own migration argues for.
 * Adding a column to a table changes neither, and re-declaring a policy here
 * would give the schema two definitions of one rule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outreach_messages', function (Blueprint $table): void {
            /*
             * When the recipient's mail server accepted the message.
             *
             * ⚠️ **NOT A STATUS AND MUST NEVER BECOME ONE.** See the class
             * docblock: `OutreachStatus::Delivered` is terminal and a reply
             * arriving after it would be dropped. What this column is for is
             * the counter and the idempotency guard behind it.
             */
            $table->timestamp('delivered_at')->nullable()->after('sent_at');

            /*
             * When a mailbox provider's feedback loop fired about this message.
             *
             * ⚠️ **THE SUPPRESSION IS SOMEWHERE ELSE AND IS PLATFORM-WIDE.**
             * `ConsentService::suppressFromCarrier()` writes `opt_outs` against
             * the address, because a complaint is a fact about the sending
             * domain every tenant shares. This column is a fact about *one
             * message*, so that the tenant's own complaint rate counts it once.
             */
            $table->timestamp('complained_at')->nullable()->after('delivered_at');

            /*
             * ⚠️ **THE COLUMN THE FEEDBACK PATH LOOKS A ROW UP BY, AND IT HAD
             * NO INDEX.** `DeliveryReceipts::apply()` has always searched this
             * column and so does `MailSendingHealth`; `messages` carries the
             * same pair and `outreach_messages` never did. Tenant first, which
             * is the order both readers query in and the order RLS filters in.
             */
            $table->index(['business_id', 'provider_msg_id']);
        });

        Schema::table('mail_tracking_codes', function (Blueprint $table): void {
            /*
             * The handle SES gives the message, echoed back on every event.
             *
             * Verified against `docs.aws.amazon.com`, *Contents of event data
             * that Amazon SES publishes to Amazon SNS* and *Amazon SNS
             * notification contents for Amazon SES*, both read 2026-08-20:
             * `mail.messageId` is *"a unique ID that Amazon SES assigned to the
             * message. Amazon SES returned this value to you when you sent the
             * message"* — and over the SMTP interface it is returned in the
             * `250 Ok {{MessageID}}` response (*SMTP response codes returned by
             * Amazon SES*, same date), which Symfony's `SmtpTransport` parses
             * into `SentMessage::getMessageId()`.
             *
             * ⚠️ **NULLABLE, BECAUSE IT IS WRITTEN AFTER THE ROW EXISTS.** The
             * code is minted on the request side, where the tenant is; the
             * message id only exists once the transport has answered, one queue
             * hop later. A row without one is a message that has not gone out
             * yet, or one whose transport named nothing.
             *
             * ⛔ **IT IS *NOT* CALLED `provider_msg_id`, AND THE DIFFERENCE IS
             * DELIBERATE — DO NOT HARMONISE THE TWO NAMES** (6369). That name
             * belongs to a **message row**, and assigning it is the event that
             * enters a message into the measured population — which is why
             * `MessagingTest`'s *"only the settlement may give a message a
             * carrier handle"* lint permits exactly one file to write it. That
             * lint reads text and cannot tell which model is being assigned, so
             * a second column of the same name anywhere in `app/` would either
             * redden it or force it to be widened to two permitted files — and
             * the second of those would then be able to assign the **outreach**
             * column with the build green. **A load-bearing single-writer lint
             * is worth more than two columns sharing a name.**
             *
             * ⚠️ **AND THE ROLES REALLY ARE DIFFERENT**, which is what makes
             * this a name rather than an evasion: on `outreach_messages` the
             * column is one tenant's record of their message's handle; here it
             * is a **resolution key**, sitting beside `code` as the second thing
             * an inbound event can name a tenant by. They hold the same string
             * and answer different questions.
             *
             * ⚠️ **UNIQUE, AND GLOBALLY SO.** An SES message id is unique across
             * the account, and the index is what makes the resolution a lookup
             * rather than a scan — but the reason it is a *constraint* is that
             * two rows answering to one id would resolve one tenant's feedback
             * onto another tenant's message, which is the one failure this
             * column exists to make impossible.
             */
            $table->string('transport_message_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('mail_tracking_codes', function (Blueprint $table): void {
            $table->dropUnique(['transport_message_id']);
            $table->dropColumn('transport_message_id');
        });

        Schema::table('outreach_messages', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'provider_msg_id']);
            $table->dropColumn(['delivered_at', 'complained_at']);
        });
    }
};
