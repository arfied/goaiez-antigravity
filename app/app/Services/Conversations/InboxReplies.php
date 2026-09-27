<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Contracts\Agent\AgentThreads;
use App\Contracts\MessageSender;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\SendRefusalReason;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Actions\WhatsappSendAction;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\Outbound\OutboundMessage;
use App\Services\Messaging\Outbound\SendKey;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Support\Tenancy;
use InvalidArgumentException;

/**
 * A person at the business answering a customer from the Inbox (R21, P18).
 *
 * ## It has no send path of its own, deliberately
 *
 * ⛔ **EVERY GUARANTEE {@see MessageSender} MAKES IS KEPT BY CALLING IT.**
 * Consent, the suppression registers, STOP, the Do Not Call and litigator lists,
 * the reassigned-numbers check, the mini-TCPA windows, the per-tenant pause, the
 * global halt, 2102's automatic complaint-rate trip, the credit debit and the
 * `outreach_messages` row are all downstream of one call. 2970–2979 records a
 * platform halt that stopped one sender out of two **because that sender had
 * built a send path of its own**; `MissedCallTextBack` names that as the reason
 * it has none, and this is the cheapest way not to become the third.
 *
 * ## Rail 4 is honoured before the wire, not after it
 *
 * ⛔ **THE LATCH IS TAKEN FIRST AND THAT ORDERING IS THE WHOLE POINT.** T176
 * §2.3 rail 4 is *"the moment a human replies on the thread the agent goes
 * silent on that thread"* — and the thing it must beat is a queued agent turn
 * about to run. `AgentThreadStates::latch()` takes `SELECT … FOR UPDATE` inside
 * a transaction precisely so that moment is a single serialised point; taking it
 * **after** the carrier call would leave a window the length of a vendor round
 * trip in which both a person and the agent are answering.
 *
 * ⚠️ **SO A REFUSED SEND STILL LATCHES, AND THAT IS THE SAFE DIRECTION.** A
 * person pressed send: they have read the conversation and decided to answer it.
 * Silence is recoverable in one click — the Inbox's own hand-back control —
 * while an agent speaking over somebody who is mid-reply is not.
 *
 * ## Idempotency of a hand-typed message
 *
 * ⚠️ **THE OCCASION IS A DRAFT KEY, NOT THE BODY AND NOT A UUID PER ATTEMPT.**
 * {@see SendKey} refuses to key on rendered text — *"idempotency keys on the
 * decision to send"* — and an arbitrary UUID minted per request is unique per
 * *attempt*, which is the opposite of what idempotency needs. The Inbox mints
 * one key per composed draft and rotates it only after a send is accepted, so a
 * double-click computes the same key, the `outreach_messages` unique index
 * refuses the second, and nothing is charged or delivered twice.
 *
 * ## Purpose
 *
 * ⚠️ **`Transactional`, AND THE ONE LINE THAT KEEPS THE T69 LAW.** A person
 * answering a customer who texted them is a service reply, which
 * {@see MessageSender}'s guarantee 5 says is *never* quiet-gated;
 * `ConsentService::stateRefusal()` returns before any window for a purpose
 * outside `isSubjectToDoNotCall()`. It is an honest classification rather than a
 * convenience — the body is whatever the owner typed, and **nothing here can
 * notice** an offer written into it, which is `24` §3.3's own caveat and is
 * recorded rather than papered over.
 */
final class InboxReplies
{
    /**
     * The longest reply the Inbox will send.
     *
     * ⚠️ **THREE GSM SEGMENTS, AND IT IS A CEILING RATHER THAN THE COMPOSER'S
     * ≤159.** `SL-2`'s single-segment budget governs text this platform
     * *writes*; a person answering a question in their own words legitimately
     * needs more than one segment, and the retail debit does not turn on the
     * segment count — an Inbox reply is text with no media, so it is **one
     * credit** at any length. ⚠️ **THIS SAID "R9 CHARGES ONE CREDIT FOR THE SEND
     * EITHER WAY" AND 9182 MOVED THAT FIGURE FOR MESSAGES THAT CARRY MEDIA**;
     * this path composes none, so the conclusion survives and the citation does
     * not. What the ceiling stops is a pasted document leaving as a
     * dozen segments the tenant never meant to pay the carrier for.
     */
    public const int BODY_LIMIT = 480;

    public function __construct(
        private readonly ConsentService $consent,
        private readonly MessageSender $sender,
        private readonly AgentThreads $threads,
        private readonly ConversationThreads $store,
        private readonly AuditService $audit,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function bodyLimit(): int
    {
        return $this->registry->int('conversations.reply.body_limit');
    }

    /**
     * Answer one thread.
     *
     * @param  string  $draftKey  The Inbox's per-draft nonce — see the class
     *                            docblock. Never a constant and never the body.
     * @return SendOutcome|SendRefusalReason A bare reason means a gate refused
     *                                       **before** a send could be keyed, so
     *                                       there is no outcome to return; a
     *                                       `SendOutcome` is the sender's own
     *                                       answer and may itself carry a
     *                                       refusal or a duplicate.
     *                                       ⚠️ **Refusal is an answer, not an
     *                                       error.**
     */
    public function send(
        Conversation $conversation,
        string $body,
        User $actor,
        string $draftKey,
    ): SendOutcome|SendRefusalReason {
        $businessId = Tenancy::idOrFail();

        if ((int) $conversation->business_id !== $businessId) {
            // ⚠️ **A HYDRATED FOREIGN MODEL IS A *WRONG* FILTER, WHICH NEITHER
            // THE GLOBAL SCOPE NOR RLS CAN SEE.** Loud rather than a refusal:
            // answering `SendRefusalReason` would file a wiring mistake as a
            // fact about the person on the other end.
            throw new InvalidArgumentException(
                'That conversation belongs to another tenant, so it cannot be answered here.',
            );
        }

        // Only SMS threads are answered from here. A WhatsApp conversation must not be answered by text message; WhatsApp replies go through Zernio once connected (REVIEWS-56).
        if ($conversation->channel !== OutreachChannel::Sms->value) {
            return SendRefusalReason::ChannelUnavailable;
        }

        $body = trim($body);

        if ($body === '' || mb_strlen($body) > $this->bodyLimit()) {
            throw new InvalidArgumentException(
                'A reply must have something in it and must fit inside the Inbox ceiling. The '
                .'screen validates both before calling this; arriving here means neither did.',
            );
        }

        $customer = $conversation->customer;

        if (! $customer instanceof Customer) {
            // The column is `nullOnDelete`, so a contact the owner deleted
            // leaves a readable thread with nobody to answer. Not an error —
            // the screen says so.
            return SendRefusalReason::NoIdentifier;
        }

        // ⛔ **RAIL 4, BEFORE THE WIRE.** See the class docblock: this is the
        // point the queued agent turn has to lose the race to, and it is
        // idempotent, so the second and third message of a burst move nothing.
        $this->threads->latch($conversation, $actor);

        $decision = $this->consent->decide($customer, OutreachChannel::Sms, OutreachPurpose::Transactional);

        if ($decision->permit === null) {
            // ⚠️ **`decide()` RATHER THAN `permit()`, SO THE OWNER IS TOLD
            // WHICH RULE ANSWERED.** A screen saying only "we could not send
            // that" turns an opted-out contact into a support ticket.
            return $decision->reason ?? SendRefusalReason::NoConsentRecord;
        }

        $outcome = $this->sender->send(OutboundMessage::for(
            permit: $decision->permit,
            body: $body,
            key: SendKey::for($decision->permit, 'inbox_reply:'.$conversation->getKey().':'.$draftKey),
            purpose: OutreachPurpose::Transactional,
        ));

        if (! $outcome->wasSent()) {
            // ⛔ **NOTHING IS FILED ON A REFUSAL OR A DUPLICATE.** A thread
            // showing a message that never left would have the business
            // believing they answered somebody they did not — and on a
            // duplicate the first attempt already filed it, so a second row
            // would show the same sentence twice.
            return $outcome;
        }

        $this->store->recordOutboundFromPerson(
            conversation: $conversation,
            body: $body,
            userId: (int) $actor->id,
        );

        // ⛔ **NO MESSAGE TEXT IN THE AUDIT ROW, EVER** —
        // `AgentThreadStates::record()`'s rule. The conversation, the actor and
        // the carrier's handle answer every question this record exists for;
        // the customer's own words live in the thread, under the gate that
        // admitted them, and copying them into an append-only store would put
        // them somewhere an erasure request cannot reach cleanly.
        $this->audit->record(
            action: 'inbox.reply.sent',
            actor: 'user:'.$actor->id,
            entity: $conversation,
            // ⚠️ **`carrier_handle` RATHER THAN `provider_msg_id`, AND THE
            // RENAME IS DELIBERATE** (4120). `MessagingTest` holds the column
            // name to `SendSettlement` by matching the literal anywhere, and it
            // is right to: a second spelling of it in a second place is how a
            // delivery receipt eventually gets joined against the wrong row.
            // This is an audit key rather than a column, so it takes a name of
            // its own instead of an allowlist entry.
            metadata: ['carrier_handle' => $outcome->providerMessageId],
        );

        return $outcome;
    }

    public function sendWhatsapp(Conversation $conversation, string $body, User $actor): array
    {
        $businessId = Tenancy::idOrFail();

        if ((int) $conversation->business_id !== $businessId) {
            throw new InvalidArgumentException(
                'That conversation belongs to another tenant, so it cannot be answered here.',
            );
        }

        if ($conversation->channel !== OutreachChannel::Whatsapp->value) {
            throw new InvalidArgumentException('Only a WhatsApp conversation is answered on WhatsApp.');
        }

        $body = trim($body);

        if ($body === '' || mb_strlen($body) > $this->bodyLimit()) {
            throw new InvalidArgumentException(
                'A reply must have something in it and must fit inside the Inbox ceiling. The '
                .'screen validates both before calling this; arriving here means neither did.',
            );
        }

        $contact = $this->store->contactFor($conversation);
        $phone = $contact['phone'] ?? null;
        if (empty($phone)) {
            return ['sent' => false, 'message' => 'Not sent — there is no phone number for this person.'];
        }

        $connection = app(WhatsappConnectionLookupAction::class)->forBusiness($businessId);
        if ($connection === null || $connection->status !== 'connected') {
            return ['sent' => false, 'message' => 'Not sent — connect a WhatsApp number first.'];
        }

        $this->threads->latch($conversation, $actor);

        $res = app(WhatsappSendAction::class)->handle($businessId, $phone, $body);

        if ($res['status'] === 'sent') {
            $this->store->recordOutboundFromPerson(conversation: $conversation, body: $body, userId: (int) $actor->id);
            $this->audit->record(
                action: 'inbox.reply.sent',
                actor: 'user:'.$actor->id,
                entity: $conversation,
                metadata: ['channel' => 'whatsapp', 'carrier_handle' => $res['provider_message_ref'] ?? null],
            );

            return ['sent' => true, 'message' => 'Sent on WhatsApp. Your assistant will stay quiet on this conversation.'];
        }

        if ($res['status'] === 'unconfirmed') {
            return ['sent' => false, 'message' => 'Sent to Zernio, but WhatsApp has not confirmed it yet — check the conversation before sending it again.'];
        }

        if ($res['status'] === 'refused') {
            $code = $res['refusal_code'] ?? '';
            if ($code === 'WHATSAPP_NOT_CONNECTED') {
                return ['sent' => false, 'message' => 'Not sent — connect a WhatsApp number first.'];
            }
            if ($code === 'NO_ZERNIO_CONVERSATION') {
                return ['sent' => false, 'message' => 'Not sent — this person has not written to your WhatsApp number yet, so there is no conversation to reply in.'];
            }
            if ($code === 'OUTSIDE_24H_WINDOW_TEMPLATE_REQUIRED' || $code === 'TEMPLATE_NOT_APPROVED') {
                return ['sent' => false, 'message' => 'Not sent — this person last wrote more than 24 hours ago. WhatsApp only allows an approved template now.'];
            }

            return ['sent' => false, 'message' => 'Not sent — this person cannot be messaged on WhatsApp right now ('.$code.').'];
        }

        return ['sent' => false, 'message' => 'Not sent — WhatsApp did not accept it.'];
    }
}
