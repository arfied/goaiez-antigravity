<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Sms\SendLogEntry;

/**
 * Asking the carrier what became of a message we already handed it.
 *
 * ⛔ **THIS IS THE SECOND HALF OF 7066 AND IT EXISTS BECAUSE A SEND WITH NO
 * OUTCOME HAD NO SECOND HALF AT ALL.** 7180 gave a campaign send whose outcome
 * nobody could establish a terminal `CampaignRecipientStatus::Unknown` — the
 * right answer, and only half of one: the row claims nothing in either
 * direction, and **nothing in this application could ever ask.** 7192(d) named
 * the missing piece precisely and did not build it, and 7198(b) put the
 * question to the owner with the note that it is *"worth building **before**
 * the first campaign rather than after"*, because the vendor's window is
 * forty-eight hours from the attempt and a row written before this shipped is
 * unanswerable for ever.
 *
 * ## Why this is a second interface and not a method on the transport
 *
 * ⛔ **`App\Contracts\Texter` IS HELD TO FIVE FILES BY THE LINT IN
 * `tests/Feature/Architecture/MessagingTest.php`, AND THAT LINT IS THE ONLY
 * THING STANDING BETWEEN A CALLER AND A CARRIER REACHED WITHOUT THE PERMIT
 * GATE** (1566). A reconciler that named `Texter` in order to ask a question
 * would be the sixth way around it, and it would read as housekeeping — which
 * is exactly what `SmsSendDriver`'s docblock refuses to do one layer down.
 *
 * **A separate contract keeps the property literally true rather than
 * approximately true.** A caller holding a `SendLogReader` cannot send: there
 * is no method here that puts anything on a wire, and the type system says so.
 * `InfobipClient` implements both, which is right — one vendor, one credential,
 * one base URL — and nothing that holds this one can reach the other one.
 *
 * ⚠️ **AND THIS CONTRACT HAS ITS OWN LINT**, for the reason the first one has:
 * a read of somebody else's system is still a vendor call with a rate limit, a
 * cost model and a customer's phone number at the other end, and the set of
 * things allowed to make it should be an enumeration rather than a habit.
 *
 * ## What a handle is, and where it comes from
 *
 * ⚠️ **THE HANDLE IS OURS AND THE TRANSPORT MINTS IT.** Infobip's
 * `destinations[].messageId` is a **caller-supplied** id — *"The ID that
 * uniquely identifies the message sent … returned in response, reports and
 * logs"* — and `GET /sms/3/logs?messageId=…` is what makes it answerable
 * afterwards. It is minted inside the driver rather than handed down from a
 * caller, and `InfobipClient::mintHandle()` gives the whole argument.
 *
 * ⚠️ **A HANDLE CARRIES NOTHING ABOUT A PERSON, AND THAT IS A REQUIREMENT
 * RATHER THAN AN OBSERVATION.** It travels to a third party and is stored in
 * their log for at least forty-eight hours. A random identifier satisfies it;
 * the `SendKey` sha256 that was the obvious thing to reuse does not, because it
 * is derived from the recipient and stable across every message we ever send
 * them.
 */
interface SendLogReader
{
    /**
     * What the carrier's own log says about each of these handles.
     *
     * ⚠️ **AN ABSENT HANDLE IS AN ABSENT KEY, NEVER A NEGATIVE.** A handle the
     * vendor cannot account for means *"this question has no answer"* and never
     * *"nothing was sent"* — three ordinary things produce it and only one of
     * them is a message that never went:
     *
     *   - the attempt fell outside the vendor's forty-eight-hour log window;
     *   - the vendor did not adopt our caller-supplied id at all, so it holds
     *     the message under a name we never learned;
     *   - the submission genuinely never reached them.
     *
     * **The caller cannot tell those apart and must not try.** A reader that
     * read a missing key as *"never sent"* would re-open a contact who may
     * already be holding the message, which is the one outcome this whole
     * mechanism exists to prevent.
     *
     * @param  list<string>  $handles  Handles this application minted, from
     *                                 `campaign_recipients.send_handle`.
     *                                 An empty list is a valid question with an
     *                                 empty answer and must not reach a wire.
     * @return array<string, SendLogEntry> Keyed by the handle asked about.
     *                                     Never contains a key that was not
     *                                     asked for.
     */
    public function outcomesFor(array $handles): array;
}
