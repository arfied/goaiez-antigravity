<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use App\Services\Consent\ConsentService;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;

/**
 * What a mailbox provider said about a message after it left — open question H.
 *
 * ⚠️ **THIS IS THE DELIVERABLE H HAS BEEN WAITING FOR SINCE STAGE 0, AND
 * PICKING A VENDOR WAS NEVER IT.** 2069: *"the webhook is the deliverable — not
 * the vendor choice … H is not closed until the handler exists."* 2094 then
 * reopened it by making Workspace primary, and its own instruction is the one
 * this class follows: *"whoever builds SL-4 owes a bounce path, and the cheapest
 * one is to send through the SES driver the seam already carries."*
 *
 * ## Which events suppress, and which deliberately do not
 *
 * Verified against `docs.aws.amazon.com`, *Contents of event data that Amazon
 * SES publishes to Amazon SNS*, read 2026-08-11:
 *
 *   `Bounce` + `bounceType: Permanent`     → suppress, `SuppressionReason::Bounce`
 *   `Bounce` + `bounceType: Transient`     → **no suppression**
 *   `Bounce` + `bounceType: Undetermined`  → **no suppression**
 *   `Complaint`                            → suppress, `SuppressionReason::Complaint`
 *
 * ⚠️ **RE-VERIFIED AGAINST THE LIVE PAGES ON 2026-08-20** — both *Contents of
 * event data that Amazon SES publishes to Amazon SNS* and *Amazon SNS
 * notification contents for Amazon SES*, because this application can receive
 * either shape and they are separate documents. Every field name above and
 * below still reads as written, and the `Delivery` object was read for the
 * first time on that date.
 *
 * ## And every one of the three now moves a counter — 6360
 *
 * ⛔ **SUPPRESSION IS NOT THE ONLY THING AN SES EVENT IS FOR, AND UNTIL 6360
 * IT WAS THE ONLY THING THIS CLASS DID WITH ONE.** 2101/2102/2113 make an
 * automatic complaint-rate trip a precondition of sending, and
 * `SendingGuard::shouldTrip(OutreachChannel::Email)` read a window with no rows
 * in it — so the trip could never fire on the channel R16 made primary. That is
 * 2496's defect: a threshold on a dead counter, with a green suite over it
 * because every test of that table seeds the counters by hand.
 *
 * ⛔ **WHICH IS WHY `Delivery` IS HANDLED HERE ALTHOUGH IT SUPPRESSES NOBODY.**
 * `SendingRates::complaintRateBp()` divides by `delivered` and
 * `hasEnoughVolume()` reads `delivered`, so a `Complaint` arm without a
 * `Delivery` arm is a numerator with no denominator — **a rate that is
 * permanently zero with complaints in it**, which is the original bug rebuilt
 * one channel over. See {@see MailSendingHealth}; the two arms are one
 * mechanism and neither is worth having alone.
 *
 * ⚠️ **THE SUPPRESSION RUNS FIRST AND THE COUNTER SECOND, DELIBERATELY.** The
 * suppression chain is the control open question H is actually about (4430) and
 * it worked before this slice; the counter is new. Ordering them this way means
 * a defect in the new code cannot stop the old one from protecting an address.
 *
 * ⚠️ **A TRANSIENT BOUNCE IS NOT A DEAD ADDRESS AND SUPPRESSING ONE IS
 * PERMANENT.** AWS's own words: *"You may be able to successfully resend to an
 * address that initially resulted in a `Transient` bounce."* `MailboxFull` and
 * `MessageTooLarge` are transient, and `SuppressionReason::Bounce` **never
 * lifts** in this application — there is no delivery feed to verify a
 * re-delivery with, which that enum's own docblock says at length. So treating
 * a full mailbox as a permanent one would silently and irreversibly remove a
 * real customer from every tenant's reach, and nothing would ever put them
 * back.
 *
 * ⚠️ **`Undetermined` IS ALSO LEFT ALONE, AND IT IS THE TEMPTING ONE.** AWS says
 * it *"was unable to determine a specific bounce reason"*, and the safe-looking
 * move is to treat unknown as bad. It is the wrong direction here for the same
 * reason: the cost of a false permanent suppression is unbounded and the cost of
 * one more attempt is a fraction of a cent.
 *
 * ⚠️ **A COMPLAINT NAMES MORE PEOPLE THAN COMPLAINED, AND WE SUPPRESS THEM
 * ANYWAY.** AWS: *"Most ISPs redact the email addresses of recipients who submit
 * complaints. For this reason, the `complainedRecipients` field includes a list
 * of everyone who was sent the email whose address is on the domain that issued
 * the complaint notification."* So one person marking us as spam can suppress
 * their colleagues. That is accepted deliberately: an over-suppression costs a
 * message that was not sent, and an under-suppression costs the sending domain's
 * reputation for every tenant at once — and `SuppressionReason::Complaint` is
 * explicit that we are not a party to a complaint and have nothing to reverse.
 *
 * ⚠️ **`not-spam` IS STILL A COMPLAINT EVENT AND IS STILL SUPPRESSED.** The
 * `complaintFeedbackType` may be `not-spam`, which reads as the opposite of a
 * complaint — it is an ISP correcting an earlier classification. The event still
 * means the provider's feedback loop fired about this message, and second-
 * guessing an ISP's own vocabulary is how a real complaint gets read as noise.
 *
 * ## The recipient field is not always a bare address
 *
 * ⛔ **AND READING IT AS ONE MADE THIS WHOLE FEED REPORT SUCCESS AND SUPPRESS
 * NOBODY** (4445). AWS: `bouncedRecipients[].emailAddress` is *"the value of
 * the `Final-Recipient` field from the DSN"* whenever a DSN is available, and
 * RFC 3464 makes that field `address-type ";" generic-address`. See
 * `bareAddress()` for the whole account; the short version is that the earlier
 * reading of this page verified the field *names* and not the field *value
 * formats*, which is the failure `CLAUDE.md` describes as verifying against a
 * summary rather than the artefact.
 *
 * ## No tenant for the suppression, and one is *resolved* for the counter
 *
 * `ConsentService::suppressFromCarrier()` rather than `suppress()`, for exactly
 * the reason that method exists: an inbound feedback event names a recipient and
 * a message id and **nothing that names a business**. `suppress()` writes
 * `suppression_list`, which is tenant-owned, and audits through
 * `AuditService::record()`, which opens with `Tenancy::idOrFail()` — so calling
 * it here does not merely file the row against an arbitrary tenant, it throws.
 * Platform scope is the correct reading rather than a fallback: a bounce is a
 * fact about an address, and a complaint is about the sending domain every
 * tenant shares.
 *
 * ⚠️ **THE HEALTH COUNTER IS THE OPPOSITE AND BOTH ARE RIGHT.** A suppression
 * removes an address from every tenant's reach, so it has no tenant; a
 * complaint *rate* is per tenant, because the pause it can trip is per tenant.
 * The tenant is **resolved and never invented** — {@see MailSendingHealth}
 * looks the SES message id up on `mail_tracking_codes`, which is un-tenanted for
 * exactly this purpose, and does its work inside `Tenancy::actingAs()`. An event
 * that resolves to nothing counts nothing, which is the ordinary case for
 * platform mail and for anything sent before that mapping existed.
 */
final class MailFeedback
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly MailSendingHealth $health,
    ) {}

    /**
     * Apply one SES event.
     *
     * Returns the number of addresses suppressed. Zero is the ordinary answer
     * for a delivery, an open, a transient bounce or a malformed payload — the
     * caller answers 200 to all of them, because none is improved by SNS
     * retrying it.
     *
     * @param  array<string, mixed>  $event
     */
    public function apply(array $event): int
    {
        // ⚠️ **TWO SPELLINGS, AND THE SECOND ONE IS NOT A LEGACY ALIAS.** AWS:
        // *"If you did not set up event publishing this field is named
        // `notificationType`."* Both are live configurations of the same topic
        // today, so reading only `eventType` silently ignores every event from
        // an account wired the other way — and the symptom is a bounce feed
        // that appears to work and suppresses nobody.
        $type = $event['eventType'] ?? $event['notificationType'] ?? null;

        return match ($type) {
            'Bounce' => $this->applyBounce($event),
            'Complaint' => $this->applyComplaint($event),
            // ⛔ **SUPPRESSES NOBODY AND IS NOT OPTIONAL** — it is the
            // denominator of the rate the automatic trip reads. See the class
            // docblock: wiring the complaint arm without this one produces a
            // complaint rate that is permanently zero, which is 2496 rebuilt.
            'Delivery' => $this->applyDelivery($event),
            default => 0,
        };
    }

    /**
     * The SES handle for the message this event is about.
     *
     * *"A unique ID that Amazon SES assigned to the message. Amazon SES returned
     * this value to you when you sent the message"* — docs.aws.amazon.com,
     * *Contents of event data that Amazon SES publishes to Amazon SNS*, read
     * 2026-08-20, and the identically-worded field on the identity-notification
     * page read the same day. **That sentence is the entire join**: what SES
     * returned at send time is what {@see MailSettlement} wrote down.
     *
     * ⚠️ **NOT `mail.headers`' `Message-ID`, WHICH IS A DIFFERENT VALUE AND IS
     * OFTEN ABSENT.** The same page: *"Any message ID within the `headers` field
     * is from the original message that you passed to Amazon SES. The message ID
     * that Amazon SES subsequently assigned to the message is in the `messageId`
     * field of the `mail` object"* — and on the identity-notification path
     * `headers` is *"only present if you configured the notification settings to
     * include the headers from the original email"*.
     *
     * @param  array<string, mixed>  $event
     */
    private function messageIdOf(array $event): ?string
    {
        $mail = $event['mail'] ?? null;

        if (! is_array($mail)) {
            return null;
        }

        $id = $mail['messageId'] ?? null;

        return is_string($id) && trim($id) !== '' ? trim($id) : null;
    }

    /**
     * A delivery — counted, and nothing else.
     *
     * ⛔ **IT MUST NOT TOUCH `OutreachStatus`.** `Delivered` is terminal, so a
     * reply arriving afterwards would be silently dropped by
     * {@see MailReplyRouter}, whose docblock names this exact event as the thing
     * that would cause it. The marker is a timestamp beside the status.
     *
     * ⚠️ **RETURNS ZERO BECAUSE ZERO ADDRESSES WERE SUPPRESSED**, which is what
     * this method's caller counts. The delivery was still recorded; the return
     * value answers a narrower question than "was this handled".
     *
     * @param  array<string, mixed>  $event
     */
    private function applyDelivery(array $event): int
    {
        $messageId = $this->messageIdOf($event);

        if ($messageId !== null) {
            $this->health->delivered($messageId);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function applyBounce(array $event): int
    {
        $bounce = $event['bounce'] ?? null;

        if (! is_array($bounce)) {
            return 0;
        }

        $bounceType = is_string($bounce['bounceType'] ?? null) ? $bounce['bounceType'] : null;
        $bounceSubType = is_string($bounce['bounceSubType'] ?? null) ? $bounce['bounceSubType'] : null;

        $messageId = $this->messageIdOf($event);

        if ($messageId !== null) {
            // ⚠️ **EVERY PUBLISHED BOUNCE COUNTS AS A FAILURE, INCLUDING A
            // TRANSIENT ONE, AND THAT DOES NOT CONTRADICT THE SUPPRESSION RULE
            // BELOW.** Suppression asks *is this address dead* and must only
            // ever answer yes for `Permanent`, because it never lifts. The
            // counter asks *did this message arrive*, and AWS only publishes
            // the transient bounces it has already stopped retrying:
            // *"`Transient` bounces are sent to you when a message has soft
            // bounced several times, and Amazon SES has stopped trying to
            // re-deliver it"* (docs.aws.amazon.com, read 2026-08-20).
            //
            // ⚠️ **THE TYPE AND SUBTYPE ARE STORED AND THE `diagnosticCode` IS
            // NOT** — `DeliveryReceipts`' rule about the vendor's status *name*
            // rather than its description, and the reason is the same: a
            // diagnostic code is a stranger's mail server quoting the
            // recipient's address into a tenant-readable column.
            $this->health->bounced($messageId, $this->bounceReason($bounceType, $bounceSubType));
        }

        if ($bounceType !== 'Permanent') {
            // Transient and Undetermined: recorded and not acted on. The count
            // is worth having — a rising transient rate is the first sign of a
            // reputation problem — and the identifier is deliberately absent.
            Log::info('A non-permanent bounce was reported and no address was suppressed.', [
                'bounce_type' => $bounceType,
                'bounce_sub_type' => $bounceSubType,
            ]);

            return 0;
        }

        return $this->suppressAll(
            $this->addressesIn($bounce, 'bouncedRecipients'),
            SuppressionReason::Bounce,
            'ses.bounce',
        );
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function applyComplaint(array $event): int
    {
        $complaint = $event['complaint'] ?? null;

        if (! is_array($complaint)) {
            return 0;
        }

        $messageId = $this->messageIdOf($event);

        if ($messageId !== null) {
            // ⛔ **THE NUMERATOR OF 2102's AUTOMATIC TRIP.** Charged to the
            // tenant that sent the message and to nobody else — which is why it
            // is keyed on the message id rather than on the address, unlike the
            // suppression below. One person's complaint suppresses the address
            // platform-wide and moves one tenant's rate.
            $this->health->complained($messageId);
        }

        return $this->suppressAll(
            $this->addressesIn($complaint, 'complainedRecipients'),
            SuppressionReason::Complaint,
            'ses.complaint',
        );
    }

    /**
     * @param  array<string, mixed>  $container
     * @return list<string>
     */
    private function addressesIn(array $container, string $key): array
    {
        $recipients = $container[$key] ?? null;

        if (! is_array($recipients)) {
            return [];
        }

        $addresses = [];

        foreach ($recipients as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }

            $address = $recipient['emailAddress'] ?? null;

            if (! is_string($address)) {
                continue;
            }

            $bare = $this->bareAddress($address);

            if ($bare !== null) {
                $addresses[] = $bare;
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * The address out of whatever shape SES echoed back.
     *
     * ⛔ **THIS METHOD DID NOT EXIST AND `trim()` WAS THE WHOLE OF IT UNTIL
     * 2026-08-16, WHICH MADE THIS FEED REPORT SUCCESS AND SUPPRESS NOBODY**
     * (4445). `Identifier::email()` accepted any string containing an `@`, so
     * `"Jane Doe" <gone@example.test>` hashed to something non-null,
     * `suppressFromCarrier()` returned true, this endpoint answered
     * `200 {"suppressed":1}` and a row appeared in `opt_outs` — while
     * `ConsentService::decide()` hashes the *bare* address and never matches
     * it. **The address stayed fully sendable and every record said otherwise.**
     * Both of `suppressFromCarrier()`'s documented fail-closed guards test the
     * hash for null; neither can tell a hash of an address from a hash of a
     * string that merely mentions one.
     *
     * ⚠️ **NOT HYPOTHETICAL — IT IS THE FIELD'S DOCUMENTED FORMAT.** AWS
     * defines `bouncedRecipients[].emailAddress` as *"The email address of the
     * recipient. **If a DSN is available, this is the value of the
     * `Final-Recipient` field from the DSN**"* (docs.aws.amazon.com, *Amazon SES
     * notification contents for Amazon SNS*, re-read against the live page
     * 2026-08-16 rather than against the earlier reading, which checked the
     * field *names* and not the field *value formats* — the exact gap
     * `CLAUDE.md` warns about). RFC 3464 §2.3.2 gives that field's syntax as
     * `"Final-Recipient" ":" address-type ";" generic-address`, with
     * `address-type = atom` — normally `rfc822`. So `rfc822; user@example.com`
     * is the conformant value, not the exotic one, and a permanent bounce
     * carrying a DSN is the ordinary case rather than the edge.
     *
     * ⚠️ **THE PREFIX IS STRIPPED ONLY WHEN WHAT PRECEDES THE `;` CONTAINS
     * NEITHER `@` NOR `"`.** An `address-type` is an atom, so it can contain
     * neither, and both exclusions are load-bearing rather than one being
     * belt-and-braces:
     *
     *   - the `@` test stops a real address whose *domain* follows a `;` from
     *     being cut in half;
     *   - ⛔ **the `"` test stops a QUOTED LOCAL PART being eaten, and the `@`
     *     test alone did not — this method shipped without it and silently
     *     missed a suppression** (4532). `"a;b"@example.test` is a legal
     *     addr-spec and RFC 3464's `generic-address` for the `rfc822`
     *     `address-type` is an addr-spec, so it is a legal `Final-Recipient`.
     *     Its first `;` is *inside* the quotes and the text before it is `"a`,
     *     which holds no `@` — so the strip fired and produced
     *     `b"@example.test`, which `Identifier::email()` correctly refuses.
     *     **The bounce was real, the address stayed sendable, and the count was
     *     honestly zero** — fail-closed, so never a phantom, but a miss on the
     *     one control open question H turns on. ⚠️ **It is reachable end to
     *     end**: `filter_var()` accepts that address, so this platform will
     *     happily store it, send to it and be bounced about it.
     *
     * A `;` arriving inside a *display name* is left for `Address` below, which
     * is what handles the delimiters a display name may carry.
     *
     * ⚠️ **`Address::create()` FOR THE WRAPPER, BECAUSE A DISPLAY NAME MAY
     * CONTAIN THE DELIMITERS.** `"Doe, Jane" <x@y>` defeats a comma split, and
     * Symfony's parser — already a dependency of this framework — handles it.
     *
     * ⚠️ **WHAT IT DOES NOT HANDLE ON ITS OWN, STATED BECAUSE IT WAS ASSERTED
     * HERE FIRST AND MEASURED SECOND** (4447, and 314–316's shape caught inside
     * the fix wave for 4445). Both verified by executing `Address::create()`
     * over every dataset value against the `symfony/mime` in this tree,
     * 2026-08-16 and again 2026-08-17, and ⚠️ **the *second* was still wrong
     * after the first pass and is corrected at 4532** — measuring one claim does
     * not measure the one beside it. ✅ **The first is now handled by this
     * method rather than accepted as a limit of the parser** (4601):
     *
     *   - **A `<` inside a quoted display name.** `Address::create()`'s pattern
     *     is `~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~` — quoted from
     *     `Address::FROM_STRING_PATTERN` rather than paraphrased, because an
     *     earlier draft of this line transcribed it with a `$` anchor it does
     *     not carry. The display name stops at the **first** `<` and the
     *     address runs to the **last** `>`, so `"Jane <the-boss>" <x@y>`
     *     captures `the-boss>" <x@y` and the constructor throws on it.
     *     ✅ **4447 ACCEPTED THAT REFUSAL AND IT NO LONGER STANDS — SEE
     *     {@see self::angleGroupAfterQuotedName()}, WHICH EXTRACTS IT** (4601).
     *     The RFC sentence 4447 rested on says the opposite of what that row
     *     said it said.
     *   - **`a@b@c`, which it returns *unchanged* rather than refusing.**
     *     ⛔ **AN RFC 822 SOURCE ROUTE WAS NAMED BESIDE IT HERE AND THAT WAS
     *     NOT MEASURED** (4532): `@relay.example.test:user@example.test` makes
     *     `Address::create()` throw `RfcComplianceException`, so it is caught
     *     below and refused by the layer underneath, not passed through by this
     *     one. The example was wrong; the gap it illustrates is real, and
     *     `a@b@c` is the case that carries it.
     *
     * The second is why the strictness lives in `Identifier::email()` and why
     * both layers are load-bearing: each catches what the other misses, and
     * neither is belt-and-braces for the other.
     *
     * ⚠️ **AN UNPARSEABLE VALUE IS RETURNED RATHER THAN DROPPED**, so that the
     * refusal happens in one place. `Identifier::email()` returns null on it,
     * `suppressFromCarrier()` answers false, and `apply()`'s count is honestly
     * zero. Dropping it here would produce the same number by a second route,
     * and two routes to one answer is how one of them stops being tested.
     *
     * ⛔ **THE CATCH IS THE COMPONENT'S INTERFACE AND NOT `RfcComplianceException`,
     * BECAUSE `Address::create()` THROWS TWO UNRELATED CLASSES AND THE FIRST
     * DRAFT OF THIS METHOD CAUGHT ONE** (4531). `create()` throws its own
     * `Mime\Exception\InvalidArgumentException` — *"Could not parse … to a
     * Symfony\Component\Mime\Address"* — when a value **contains `<` and does
     * not match `FROM_STRING_PATTERN`**, and `RfcComplianceException` only
     * later, from the constructor. **Both were observed**: an unterminated
     * bracket gives the first, `"Jane <the-boss>" <x@y>` the second. Both
     * extend `\InvalidArgumentException` and neither extends the other; only
     * `Mime\Exception\ExceptionInterface` covers both.
     *
     * ⚠️ **THE COST OF MISSING IT WAS NOT A WRONG ANSWER, IT WAS A LOOP.** An
     * unterminated bracket — `rfc822; <gone@example.test`, which is what a
     * truncated DSN header gives — escaped `apply()`, 500'd
     * `SesFeedbackController`, and **SNS retries a 500 indefinitely**. One
     * malformed recipient would have become a permanent redelivery loop against
     * the endpoint the entire bounce feed depends on. Found by fuzzing this
     * method rather than by reading it.
     */
    private function bareAddress(string $raw): ?string
    {
        $value = trim($raw);

        if ($value === '') {
            return null;
        }

        $semicolon = strpos($value, ';');

        if ($semicolon !== false) {
            $prefix = substr($value, 0, $semicolon);

            if (! str_contains($prefix, '@') && ! str_contains($prefix, '"')) {
                $value = trim(substr($value, $semicolon + 1));
            }
        }

        if ($value === '') {
            return null;
        }

        // ⛔ **THE PHANTOM GUARD, AND IT RUNS BEFORE THE PARSER RATHER THAN
        // AFTER IT** (4602). See {@see self::bracketedAddressIsInsideQuotes()}:
        // `Address::create()` will happily read an address out of the *inside*
        // of a quoted display name, which suppresses somebody who was never the
        // recipient.
        if ($this->bracketedAddressIsInsideQuotes($value)) {
            return $value;
        }

        try {
            return Address::create($value)->getAddress();
        } catch (MimeException) {
            $tail = $this->angleGroupAfterQuotedName($value);

            if ($tail !== null) {
                try {
                    return Address::create($tail)->getAddress();
                } catch (MimeException) {
                    // Left for `Identifier::email()` to refuse. See above.
                    return $value;
                }
            }

            // Left for `Identifier::email()` to refuse. See above.
            return $value;
        }
    }

    /**
     * Whether this value's `<…>` closes inside the display name.
     *
     * ⛔ **THIS EXISTS BECAUSE THE PARSER PRODUCES A PHANTOM SUPPRESSION ON ONE
     * SHAPE, MEASURED BY EXECUTION 2026-08-17 AND NOT PREDICTED BY READING**
     * (4602). `FROM_STRING_PATTERN` is
     * `~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~` — read off
     * `Address::FROM_STRING_PATTERN` by reflection against the `symfony/mime`
     * in this tree rather than transcribed, which is 4532's (c) — and it is
     * **unanchored**, so the trailing `[^>]*` absorbs whatever follows the last
     * `>`. For `"Jane <boss@corp.test>"` — a display name carrying an address
     * and no recipient at all — `Address::create()` returns
     * **`boss@corp.test`**, `Identifier::email()` accepts it because it is a
     * perfectly good address, and this application suppresses somebody who
     * never bounced and was never written to, platform-wide and for ever.
     *
     * ⚠️ **THAT IS THE ONE DIRECTION THIS FILE HAS NEVER ACCEPTED.** 4445's
     * finding is that a phantom reports success while a miss reports an honest
     * zero; every refusal here is chosen on that grounds, and this shape was
     * quietly reaching the other outcome.
     *
     * The test is positional and needs no parser: **an address whose `>` closes
     * before the last `"` closes is inside the quotes.** A genuine display name
     * is followed by its address, so its last `"` always precedes the final `>`.
     * Measured in both directions over the corpus: `"Jane" <x@y.test>` and
     * `"Jane <the-boss>" <x@y.test>` pass it, `"Jane <boss@corp.test>"` and
     * `"<boss@corp.test>"` are refused by it.
     */
    private function bracketedAddressIsInsideQuotes(string $value): bool
    {
        $quote = strrpos($value, '"');
        $close = strrpos($value, '>');

        return $quote !== false && $close !== false && $close < $quote;
    }

    /**
     * The final `<…>` group, when everything before it is a quoted display name.
     *
     * ✅ **THIS IS 4447's REFUSAL LIFTED, AND THE GROUND IT STOOD ON DID NOT
     * SURVIVE RE-READING THE RFC** (4601). 4447 accepted refusing
     * `"Jane <the-boss>" <x@y.test>` whole, arguing that *"a `Final-Recipient`
     * is a bare mailbox address by RFC 3464, so this shape can only arrive from
     * an MTA already writing something the specification does not describe"*.
     * RFC 3464 §2.3.2, re-fetched from `rfc-editor.org` 2026-08-17, says the
     * opposite in a NOTE that quoting the grammar alone skips: *"The Reporting
     * MTA is **not expected to ensure that the address actually conforms to the
     * syntax conventions of the address-type**. Instead, it MUST report exactly
     * the address received in the envelope"* — and §2.1.2 gives the field as
     * `generic-address = *text`. **A non-conformant value here is what the
     * specification tells you to expect**, so declining to parse one is not the
     * conservative reading of the RFC; it is a reading the RFC contradicts.
     *
     * ⚠️ **AND THE EXTRACTION IS NOT A SECOND PARSER.** Symfony's pattern
     * already ends the address at the **last** `>`; the only thing it gets
     * wrong here is starting the display name at the **first** `<`. So this
     * re-enters `Address::create()` on the suffix beginning at the last `<`,
     * and the addr-spec match, the constructor's RFC compliance check and
     * `Identifier::email()` beneath it all still run on the result.
     *
     * ⛔ **THE QUOTED-PREFIX CONDITION IS WHAT KEEPS IT FROM BECOMING A GUESS**,
     * and it is deliberately narrower than *"take the last angle group"*.
     * `Jane <a@b.test> <c@d.test>` names two addresses and choosing the second
     * is a coin toss — a coin toss that suppresses is the phantom returning by
     * another door. That value has no quoted prefix, so it stays refused whole.
     * **The retry fires only for the shape 4447 named: a quoted display name
     * containing a `<`.**
     */
    private function angleGroupAfterQuotedName(string $value): ?string
    {
        $open = strrpos($value, '<');

        if ($open === false) {
            return null;
        }

        $name = trim(substr($value, 0, $open));

        if (strlen($name) < 2 || ! str_starts_with($name, '"') || ! str_ends_with($name, '"')) {
            return null;
        }

        return substr($value, $open);
    }

    /**
     * The bounce's classification, as one readable string.
     *
     * ⚠️ **BOTH HALVES OR NEITHER IS USEFUL.** `Permanent` alone does not say
     * whether the mailbox does not exist or the address is on SES's own
     * suppression list, and `General` alone does not say whether it was
     * permanent. Null when SES named neither, so the column stays empty rather
     * than acquiring a placeholder that reads like a vendor value.
     */
    private function bounceReason(?string $type, ?string $subType): ?string
    {
        $parts = array_values(array_filter([$type, $subType], is_string(...)));

        return $parts === [] ? null : implode('/', $parts);
    }

    /**
     * @param  list<string>  $addresses
     */
    private function suppressAll(array $addresses, SuppressionReason $class, string $actor): int
    {
        $suppressed = 0;

        foreach ($addresses as $address) {
            // ⚠️ Returns false on an identifier that will not normalise, which
            // is a real state for an address SES echoes back from a malformed
            // header. Counted rather than thrown: one unparseable recipient in
            // a multi-recipient bounce must not discard the ones that parsed.
            if ($this->consent->suppressFromCarrier($address, OutreachChannel::Email, $class, $actor)) {
                $suppressed++;
            }
        }

        return $suppressed;
    }
}
