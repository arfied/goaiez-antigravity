<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Enums\MessageDirection;
use App\Enums\MessageSenderType;
use App\Enums\OutreachChannel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Modules\X121\Actions\EntityReadAction;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The tenant's SMS threads — the store the Inbox (R21, P18) reads and writes.
 *
 * ⛔ **THIS CLOSES DECISION 272's SHAPE ON `conversations` AND `messages`.** Both
 * tables have existed since 2026-07-30 and **neither had a writer anywhere in
 * `app/`** until this class: `InboundMessages` recorded an `inbound_messages`
 * row and stopped, the support desk threads onto `SupportThread`, and P3's agent
 * thread state maintained five columns on a table nothing created a row in. An
 * isolation test against a table nothing writes passes perfectly, which is the
 * tell CLAUDE.md names, and P3's own docblock said so in as many words.
 *
 * ## Where a thread comes from, and where it deliberately does not
 *
 * ✅ **A CUSTOMER'S OWN TEXT OPENS ONE** — {@see InboundThreading}, called from
 * the carrier webhook. That is the only automatic opener, and it is the honest
 * one: the tenant is a lookup on *our* number (dedicated number allocation), the contact is a lookup on
 * *their* customer list, and the words are the customer's own.
 *
 * ⛔ **AN OUTBOUND SEND DOES NOT OPEN ONE, AND THAT IS A REFUSAL RATHER THAN AN
 * OVERSIGHT.** Threading every review invite and every missed-call text-back
 * would fill the Inbox with rows nobody can reply into — the reply needs an
 * inbound half that only dedicated number allocation can produce — and would quietly
 * reclassify a one-way compliance send as a conversation.
 * `MissedCallTextBack` is where that would go and it is left alone; recorded as
 * owed at decision 4118.
 *
 * ## The tenant boundary
 *
 * ⚠️ **NOTHING OUTSIDE THIS CLASS NAMES `conversations` OR `messages` ON THE
 * INBOX PATH**, which is `SupportDesk`'s shape and is held by a chokepoint lint.
 * Every read runs through the global scope and RLS beneath it, in one place,
 * rather than through a `where` a screen could forget.
 *
 * ⚠️ **AND A HYDRATED FOREIGN MODEL IS REFUSED EXPLICITLY**, because neither
 * layer can see one — `AgentThreadStates::refuseForeignThread()` is the
 * precedent and the reasoning is identical: RLS catches a *forgotten* filter,
 * never a *wrong* one.
 *
 * ## Message bodies, and the gate the table declares
 *
 * ⚠️ **`conversations.consent_logged_at` IS THE GATE ON STORING BODIES AT ALL**
 * — the creating migration says so, citing `29` §2 rule 22. That rule was read
 * verbatim before this class was written and **its subject is the chat widget**:
 * *"Chat widget consent is mandatory… pre-chat notice, logged consent,
 * first-party transcripts only, no capture before consent."* CIPA's hazard is
 * capture the parties did not know about; a person texting a business's
 * published number knows exactly who they are writing to.
 *
 * ⛔ **THE GATE IS STILL ANSWERED RATHER THAN BYPASSED** (4113). An SMS thread is
 * stamped at the moment it opens, and what the stamp means on this channel is
 * written down here rather than inferred later: *the customer addressed this
 * message to this business's own number.* It is never copied from a consent
 * record — a consent record authorises a **send**, and reusing it as a capture
 * notice would be the manufactured artefact `MissedCallTextBack` refuses.
 */
final class ConversationThreads
{
    /**
     * How many threads the list renders.
     *
     * ⚠️ **A CAP RATHER THAN A PAGINATOR, AND IT IS THE HONEST SHAPE TODAY.**
     * `Support` does the same for the same reason: a second page is a control
     * with nothing behind it until a tenant has more threads than this, and the
     * inbound writer can only produce threads for a tenant holding their own dedicated
     * number. The day that changes this becomes a paginator.
     */
    public const int LIST_LIMIT = 100;

    /**
     * How many messages one thread shows.
     *
     * The whole conversation, up to a ceiling that stops one runaway thread
     * rendering a megabyte of customer content into a page.
     */
    public const int THREAD_LIMIT = 200;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * Every SMS thread this tenant has, newest activity first.
     *
     * @return Collection<int, Conversation>
     */
    public function list(): Collection
    {
        return Conversation::query()
            ->whereIn('channel', [OutreachChannel::Sms->value, OutreachChannel::Whatsapp->value])
            ->with('customer')
            // Newest first on `updated_at`, which `touch()` moves on every
            // message. Ordering on the message table would need a join no
            // global scope reaches.
            //
            // ⚠️ **`NULLS LAST` OUT LOUD, BECAUSE POSTGRES PUTS NULLS FIRST ON A
            // DESC SORT** and `updated_at` is nullable. A plain
            // `orderByDesc('updated_at')` would float every undated thread to
            // the top of the Inbox — the rows a business owner is most likely to
            // read first would be the ones nothing has happened to.
            // `ConventionsTest`'s lint is what found this.
            ->orderByRaw('updated_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->limit($this->defaults->int('conversations.list_limit'))
            ->get();
    }

    /**
     * One thread of this tenant's, or null.
     *
     * ⚠️ **NULL RATHER THAN AN EXCEPTION**, because the id arrives from the
     * browser: a Livewire action is callable whatever rendered it (391), so
     * another tenant's id is one line to attempt and finds nothing. The screen
     * answers 404; it is not an error worth a stack trace.
     */
    public function find(int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->whereIn('channel', [OutreachChannel::Sms->value, OutreachChannel::Whatsapp->value])
            ->with('customer')
            ->find($conversationId);
    }

    public function findSocial(int $conversationId): ?Conversation
    {
        return Conversation::query()
            ->whereIn('channel', ['facebook', 'instagram'])
            ->with('customer')
            ->find($conversationId);
    }

    public function socialThreads(): Collection
    {
        return Conversation::query()
            ->whereIn('channel', ['facebook', 'instagram'])
            ->with('customer')
            ->orderByRaw('updated_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->limit($this->defaults->int('conversations.list_limit'))
            ->get();
    }

    public function socialThread(string $platform, string $conversationRef, string $accountRef, ?string $label): Conversation
    {
        if (! in_array($platform, ['facebook', 'instagram'], true)) {
            throw new InvalidArgumentException("Platform must be facebook or instagram, got $platform");
        }

        $businessId = Tenancy::idOrFail();

        /** @var Conversation $thread */
        $thread = DB::transaction(function () use ($platform, $conversationRef, $accountRef, $label, $businessId): Conversation {
            $existing = Conversation::query()
                ->where('channel', $platform)
                ->where('provider_conversation_ref', $conversationRef)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Conversation) {
                if ($existing->contact_label === null && $label !== null) {
                    $existing->update(['contact_label' => $label]);
                }

                return $existing;
            }

            return Conversation::query()->create([
                'business_id' => $businessId,
                'channel' => $platform,
                'status' => 'open',
                'priority' => 'normal',
                'is_bot_handled' => false,
                'provider_conversation_ref' => $conversationRef,
                'provider_account_ref' => $accountRef,
                'contact_label' => $label,
                'consent_logged_at' => now(),
            ]);
        });

        return $thread;
    }

    public function contactFor(Conversation $conversation): ?array
    {
        $this->refuseForeignThread($conversation);

        if ($conversation->customer !== null) {
            return [
                'name' => $conversation->customer->name,
                'phone' => $conversation->customer->phone,
            ];
        }

        if ($conversation->person_id !== null) {
            $person = app(EntityReadAction::class)->handle('people', (int) $conversation->person_id, (int) $conversation->business_id);
            if ($person === null) {
                return null;
            }

            $name = trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? ''));

            return [
                'name' => $name === '' ? null : $name,
                'phone' => $person['phone'] ?? null,
            ];
        }

        return null;
    }

    /**
     * The messages on one thread, oldest first.
     *
     * @return Collection<int, Message>
     */
    public function messages(Conversation $conversation): Collection
    {
        $this->refuseForeignThread($conversation);

        return Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('id')
            ->limit(self::THREAD_LIMIT)
            ->get();
    }

    /**
     * The last `$limit` messages on one thread that carry a body, oldest first.
     *
     * ⛔ **THIS EXISTS SO THE THREAD-CLOSE SUMMARISER IS NOT A SECOND READER OF
     * `messages`** (4289). P13 read the table directly — `Message::query()
     * ->where('conversation_id', …)` — which is the one thing this class's lint
     * refuses, and for the reason its header gives: message bodies are the most
     * sensitive free text in the system, so a second reader is a second place a
     * tenant filter can be forgotten and a second place PHI can leave from. It
     * was invisible to P18 because P13 was on another branch.
     *
     * ⚠️ **NOT {@see self::messages()} WITH A SLICE, AND THE DIFFERENCE IS NOT
     * COSMETIC.** That method takes the *first* `THREAD_LIMIT` rows because a
     * screen reads a thread from the top; a summary needs the *tail*, so taking
     * the last twenty of `messages()`' answer would transcribe the **opening**
     * of any thread longer than 200 messages and describe it as what happened.
     * The ordering is done in SQL and reversed in PHP for that reason.
     *
     * ⚠️ **BODYLESS ROWS ARE EXCLUDED IN THE QUERY, NOT AFTER IT.** An MMS with
     * no text is a row here, and filtering it in PHP would spend the caller's
     * whole budget on messages with nothing to say.
     *
     * ✅ **AND IT INHERITS `refuseForeignThread()`**, which the direct read had
     * no equivalent of — a thread handed in from another tenant now throws
     * rather than being transcribed into an email.
     *
     * @return Collection<int, Message>
     */
    public function tail(Conversation $conversation, int $limit): Collection
    {
        $this->refuseForeignThread($conversation);

        return Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->whereNotNull('body')
            ->orderByDesc('id')
            ->limit(min($limit, self::THREAD_LIMIT))
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * The tenant's open SMS thread with this contact, opening one if there is
     * none.
     *
     * ⚠️ **FIND-OR-CREATE UNDER A TRANSACTION, KEYED ON THE CONTACT.** Two
     * inbound messages arriving together must not produce two threads for one
     * person — the Inbox would show the same conversation twice and the latch
     * would apply to whichever copy the reader happened to open. There is no
     * unique index to lean on, because a contact legitimately has many *closed*
     * threads over time, so the serialisation is the transaction.
     */
    public function openFor(Customer $customer): Conversation
    {
        $businessId = Tenancy::idOrFail();

        if ((int) $customer->business_id !== $businessId) {
            throw new InvalidArgumentException(
                'That contact belongs to another tenant, so a thread cannot be opened with them.',
            );
        }

        /** @var Conversation $thread */
        $thread = DB::transaction(function () use ($customer, $businessId): Conversation {
            $existing = Conversation::query()
                ->where('customer_id', $customer->getKey())
                ->where('channel', OutreachChannel::Sms->value)
                ->whereNull('resolved_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof Conversation) {
                return $existing;
            }

            return Conversation::query()->create([
                'business_id' => $businessId,
                'location_id' => $customer->location_id,
                'customer_id' => $customer->getKey(),
                'channel' => OutreachChannel::Sms->value,
                // ⚠️ **NO SUBJECT.** A text message has none, and deriving one
                // from the first line would put customer content in a column
                // the list renders as a heading.
                'subject' => null,
                'status' => 'new',
                'priority' => 'normal',
                // ⛔ **`false` RATHER THAN THE COLUMN DEFAULT OF `true`.**
                // Nothing dispatches `GroundAgentTurnJob` anywhere in `app/`, so
                // a thread claiming a bot is handling it would be the exact
                // false render P3's deleted stub was removed for. What this
                // screen reads is `agent_status`, and it starts `Unhandled` —
                // *"waiting for a first reply"*, which is true.
                'is_bot_handled' => false,
                // The gate the table declares, answered at the moment it
                // becomes true. See the class docblock for what it means here.
                'consent_logged_at' => now(),
            ]);
        });

        return $thread;
    }

    /**
     * File what the customer said.
     */
    public function recordInbound(Conversation $conversation, string $body): Message
    {
        return $this->record(
            $conversation,
            MessageDirection::Inbound,
            MessageSenderType::Customer,
            null,
            $body,
        );
    }

    /**
     * File what a person at the business sent.
     *
     * ⚠️ **THE ACTOR IS A USER ID AND NOT A NAME.** `messages.sender_id` is read
     * back by the thread view, which renders the *role* — a colleague's name on
     * a customer conversation is an `audit_log` question, and the audit row is
     * written by the latch rather than by this.
     */
    public function recordOutboundFromPerson(Conversation $conversation, string $body, int $userId): Message
    {
        return $this->record(
            $conversation,
            MessageDirection::Outbound,
            MessageSenderType::Person,
            (string) $userId,
            $body,
        );
    }

    /**
     * The one insert.
     *
     * ⚠️ **THE THREAD IS TOUCHED IN THE SAME TRANSACTION AS THE MESSAGE.** The
     * list orders on `updated_at`, so a message committed without the touch
     * would land a customer's reply at the bottom of the Inbox — the one place
     * a business owner would never look for it.
     */
    private function record(
        Conversation $conversation,
        MessageDirection $direction,
        MessageSenderType $senderType,
        ?string $senderId,
        string $body,
    ): Message {
        $this->refuseForeignThread($conversation);

        if (trim($body) === '') {
            throw new InvalidArgumentException(
                'An empty message is not a message. Filing one would put a blank line in a '
                .'conversation with nothing to say what was meant by it.',
            );
        }

        if (! $conversation->hasLoggedConsent()) {
            // ⛔ **THE TABLE'S OWN GATE, ENFORCED RATHER THAN DESCRIBED.** A
            // thread that has not been cleared to store bodies does not store
            // one, whatever opened it. `openFor()` stamps it; a thread from any
            // other source that has not is refused here rather than silently
            // written.
            throw new InvalidArgumentException(
                'This thread has not been cleared to store message content, so nothing may be '
                .'filed against it.',
            );
        }

        /** @var Message $message */
        $message = DB::transaction(function () use (
            $conversation,
            $direction,
            $senderType,
            $senderId,
            $body,
        ): Message {
            $message = Message::query()->create([
                'business_id' => Tenancy::idOrFail(),
                'conversation_id' => $conversation->getKey(),
                'direction' => $direction,
                'sender_type' => $senderType,
                'sender_id' => $senderId,
                'body' => $body,
                // ⛔ **NO `provider_msg_id`, AND THAT IS A REFUSAL RATHER THAN
                // AN OMISSION** (4120). `MessagingTest` holds that column to
                // `SendSettlement`, because *"every message that gets a
                // `provider_msg_id` enters the population a delivery receipt
                // can land on"* — the denominator of
                // `SendingRates::deliveryRateBp()`. That reasoning is about
                // `outreach_messages` and this is a different table, so the
                // hazard does not actually reach here — but the column would
                // have had **no reader anywhere**, which is 272's shape, and a
                // second table carrying the same column name is an invitation
                // to join a delivery receipt against the wrong one. The send
                // ledger already holds the carrier's handle; a thread row that
                // needs it can be joined through `outreach_messages` when
                // something needs to.
                'attachments' => [],
                'created_at' => now(),
            ]);

            $this->touch($conversation, $direction);

            return $message;
        });

        return $message;
    }

    /**
     * Move the thread's own timestamps for one message.
     *
     * ⛔ **NO `agent_*` COLUMN IS TOUCHED HERE AND NONE MAY BE.** Rail 4's five
     * columns have exactly one writer — `AgentThreadStates` — and
     * `Architecture/AgentTest` fails the build on a second. A convenient status
     * set beside this update is precisely the *"second place that sets the same
     * column"* the contract names.
     */
    private function touch(Conversation $conversation, MessageDirection $direction): void
    {
        $attributes = ['updated_at' => now()];

        if ($direction === MessageDirection::Outbound && $conversation->first_response_at === null) {
            // The desk's own SLA column, and the first outbound message is what
            // it means. Written here because this is the only place a first
            // response can be observed.
            $attributes['first_response_at'] = now();
        }

        $conversation->forceFill($attributes)->save();
    }

    /**
     * Refuse a thread belonging to another tenant, loudly and before any write.
     *
     * ⚠️ **THE APPLICATION LAYER'S CONTRIBUTION, AND NOT THE ONLY ONE.** The
     * global scope and the RLS policy both hide the row; this exists because a
     * caller can hand in a *hydrated* model loaded under a different tenant,
     * which no scope and no policy can see — `AgentThreadStates`' precedent,
     * because the hazard is the same one.
     */
    private function refuseForeignThread(Conversation $conversation): void
    {
        $businessId = Tenancy::idOrFail();

        if (! $conversation->exists) {
            throw new InvalidArgumentException(
                'A thread must be saved before anything can be filed against it.',
            );
        }

        if ((int) $conversation->business_id !== $businessId) {
            throw new InvalidArgumentException(
                'That conversation belongs to another tenant, so it cannot be read or written here.',
            );
        }
    }
}
