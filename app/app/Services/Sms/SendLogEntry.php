<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Enums\CarrierVerdict;
use Illuminate\Support\Carbon;

/**
 * One line of the carrier's own log about a message we handed it.
 *
 * ⚠️ **IT IS EVIDENCE THAT THE CARRIER HAS THE MESSAGE, NOT EVIDENCE THAT A
 * HANDSET RECEIVED IT.** {@see SentText} makes the same distinction about the
 * submission response and for the same reason: reading either as proof of
 * delivery is decision 620's inversion. What this object settles is the
 * question `CampaignRecipientStatus::Unknown` could not answer — *did this send
 * happen at all* — and nothing beyond it.
 *
 * ⛔ **THE VENDOR'S VOCABULARY IS INTERPRETED BY THE DRIVER AND NOWHERE ELSE,
 * AND THE ANSWER ARRIVES HERE ALREADY DECIDED.** {@see InfobipClient} owns that
 * list — `REJECTED` and `UNDELIVERABLE` are its `REFUSED_GROUPS`, and the
 * **same constant** is what refuses a message at submission time — so
 * {@see self::$verdict} is one reading of one list rather than two readings
 * that could drift. A campaign service that matched on `'REJECTED'` itself
 * would be the second copy, in the layer least able to notice when the vendor
 * adds a state. A lint in `tests/Feature/Architecture/MessagingTest.php` holds
 * that vocabulary to one file.
 *
 * ⛔ **THAT PARAGRAPH CARRIED A `bool $carrierTookIt` UNTIL 2026-08-22, AND ONE
 * BIT COULD NOT SAY WHAT THE VENDOR HAD SAID** (7500). `REFUSED_GROUPS` names
 * two groups that mean opposite things on this path — `REJECTED` is *"we would
 * not take it"* and **`UNDELIVERABLE` is *"we took it and could not deliver
 * it"*** — and the bit was `false` for both, so a refusal count included
 * messages the carrier had accepted and a future re-opened audience would have
 * texted those people twice. **The reading is now {@see CarrierVerdict}**, four
 * cases decided in the same one file, and the fourth of them is the one this
 * object could not express at all: a log line carrying *no* status was `false`
 * too, which read a silence as a refusal.
 *
 * ⚠️ **NO DESTINATION, NO BODY, NO PRICE.** `GET /sms/3/logs` answers with the
 * recipient's number, the message text and what it cost, and none of the three
 * has any business leaving the transport: `TextNotDeliverable`'s class docblock
 * and `VendorLog`'s allowlist say why, and a value object that carried a
 * customer's mobile number into a campaign service would put it one careless
 * log line from `storage/logs`.
 */
final readonly class SendLogEntry
{
    public function __construct(
        /**
         * The handle we asked about, which is also the carrier's name for this
         * message.
         *
         * ⛔ **THERE IS DELIBERATELY NO SECOND `providerMessageId` FIELD BESIDE
         * THIS ONE, AND THE FIRST DRAFT HAD ONE** (7371). It carried a docblock
         * saying the vendor's id was *"read back rather than assumed equal to
         * the handle"* — and the code assigned the same variable to both,
         * because {@see InfobipClient} looks these entries up **by**
         * `messageId` and discards any result whose `messageId` is not one it
         * asked for. The two were equal by construction, so the second field
         * had no independent writer (272's shape) and its docblock was a claim
         * about a mechanism that did not exist (314–316).
         *
         * ⚠️ **THE FACT UNDERNEATH IS REAL AND IS WHY THIS IS SAFE TO WRITE
         * INTO `campaign_recipients.provider_message_id`**: once a caller
         * supplies `destinations[].messageId`, the id we supplied *is* the name
         * the vendor uses for the message — *"returned in response, reports and
         * logs"*, `Destination.messageId`, raw OpenAPI version `3.222.1`,
         * fetched 2026-08-22. If the vendor ever stopped adopting it, this
         * lookup would find nothing at all rather than find a different name.
         */
        public string $handle,
        /**
         * The vendor's own word for this message, already read.
         *
         * ⛔ **THE ONE FIELD A CALLER MAY BRANCH ON, AND THE REASON IS THAT THE
         * BRANCHING WAS DONE HERE** — in the driver, over the vendor's list, in
         * the file the lint pins. {@see CarrierVerdict} carries the whole
         * argument, including why `UNDELIVERABLE` sits on the *took it* side of
         * every question this application asks and `REJECTED` does not.
         *
         * ⚠️ **AN UNRECOGNISED GROUP IS {@see CarrierVerdict::Took}, WHICH IS
         * THE DRIVER'S EXISTING POSTURE AND NOT A NEW ONE**: *"an unknown group
         * name is treated as accepted … because refusing on an unrecognised
         * label would turn a vendor adding a state into an outage."*
         * ⚠️ **A group of `null` is {@see CarrierVerdict::Unstated}**, because
         * that is not a documented shape at all and *no answer* is a different
         * thing from *an answer we do not recognise* — and a different thing
         * again from the refusal it used to be counted as.
         */
        public CarrierVerdict $verdict,
        /**
         * The vendor's `status.groupName`, upper-cased and otherwise verbatim.
         *
         * Its documented vocabulary is `ACCEPTED`, `PENDING`, `UNDELIVERABLE`,
         * `DELIVERED`, `EXPIRED`, `REJECTED` (`MessageGeneralStatus`, raw
         * OpenAPI `https://api.infobip.com/platform/1/openapi/sms`, version
         * `3.222.1`, fetched 2026-08-22). Null when the log entry carried no
         * status at all, which is not a documented shape and is therefore
         * treated as *"no answer"* rather than as any particular answer.
         *
         * ⚠️ **CARRIED FOR THE LOG LINE AND FOR NOTHING ELSE.** The decision is
         * {@see self::$verdict}'s; this is what an operator reads when
         * they want to know *which* answer it was. A caller that branched on
         * this string would be the second interpretation this class exists to
         * prevent.
         */
        public ?string $statusGroup,
        /**
         * When the carrier says the message was sent.
         *
         * Null when the log entry carried no `sentAt`. The caller needs a time
         * it can stand behind before it may write `sent_at`, and inventing
         * `now()` for a message sent an hour ago would put a marketing touch in
         * the wrong day for `SendCollisionArbiter`.
         */
        public ?Carbon $sentAt = null,
    ) {}
}
