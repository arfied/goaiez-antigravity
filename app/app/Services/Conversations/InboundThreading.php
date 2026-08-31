<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Enums\OutreachChannel;
use App\Models\Customer;
use App\Services\Sms\TenantNumbers;
use App\Support\Identifier;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A customer's own text becoming a thread the business can answer — the writer
 * `conversations` never had.
 *
 * ⛔ **THIS IS THE WRITER DECISION OF P18, AND IT IS DELIBERATELY THE ONLY
 * AUTOMATIC ONE.** An Inbox over a table nothing writes renders an empty screen
 * for every tenant while every test of it passes — CLAUDE.md's decision-272
 * shape, and P3 recorded `conversations` as having no writer anywhere in `app/`.
 * The Inbox is built against a store that is filled by a real event rather than
 * against a contract nothing satisfies.
 *
 * ## Three refusals, and the first one is not small
 *
 * ⛔ **NO TENANT, NO THREAD — AND ON THE SHARED LANE A POOL NUMBER THERE IS NO
 * TENANT.** {@see TenantNumbers::tenantFor()} is R8's reverse lookup and answers
 * `null` for a number nobody owns. `InboundMessages`' own docblock is the
 * authority for why that may never be papered over: *"a tenant inferred from
 * who last messaged this person is wrong the first time somebody is a customer
 * of two tenants"* — and here being wrong means **showing one business another
 * business's customer's words**, which is a cross-tenant leak of the most
 * sensitive free text in the system. So a text arriving on the shared pool
 * number is recorded in `inbound_messages` exactly as before and threads
 * nowhere.
 *
 * ⚠️ **THE CONSEQUENCE IS STATED RATHER THAN LEFT TO BE DISCOVERED FROM A FLAT
 * SCREEN**: until a tenant holds their own number under R8, their Inbox is
 * empty. That is a provisioning gap, not a defect in this class, and it is the
 * same gap that under-reports the complaint rate two paragraphs further down
 * that file.
 *
 * ⛔ **NO CONTACT, NO THREAD.** The customer is a lookup on the tenant's own
 * list, never a row this class creates — `MissedCallTextBack`'s rule, for its
 * reason: *"creating one here would store a stranger's mobile number on a basis
 * nobody has established."* A stranger texting the business is recorded and
 * answered by the compliance path; it does not become a contact.
 *
 * ⚠️ **AND THE MATCH IS ON THE NORMALISED NUMBER, WHICH IS NARROWER THAN IT
 * LOOKS.** `FeedbackSubmission::findByIdentifier()` is the established
 * convention and this follows it: a contact whose stored `phone` was typed in
 * some other format does not match, and their text threads nowhere rather than
 * threading against the wrong person. Fail-closed is the correct direction here.
 *
 * ## Only ordinary text
 *
 * ⛔ **STOP, START AND HELP NEVER THREAD.** They are compliance instructions
 * with their own handling, their own audit trail and their own carrier-required
 * replies; putting them in a conversation would invite a business owner to
 * answer a withdrawal of consent conversationally, which is the one reply that
 * must never be sent. `InboundKeyword::None` — somebody replying to us in words
 * — is the whole population of this path.
 *
 * ## It never breaks the webhook
 *
 * ⚠️ **EVERY FAILURE IS SWALLOWED, FOR `countAgainstSender()`'s REASON.** This
 * runs inside a carrier webhook: an exception escaping turns a 200 into a 500,
 * Infobip answers a 500 by redelivering, and the `inbound_messages` unique index
 * will refuse the replay — so the retry buys nothing and costs every *other*
 * message in the same delivery batch. **The suppression and the `inbound_messages`
 * row have both already landed by the time anything here can throw**, which is
 * what makes swallowing affordable at this point and would not make it
 * affordable one line earlier.
 */
final class InboundThreading
{
    public function __construct(
        private readonly TenantNumbers $numbers,
        private readonly ConversationThreads $threads,
    ) {}

    /**
     * Thread one ordinary inbound text, or decline to.
     *
     * Returns the filed message — thread, row id and body together — or **null
     * when it threaded nowhere**, which covers every refusal below and is the
     * common case today.
     *
     * ⚠️ **THE RETURN IS THE JOIN KEY FOR THE CAMPAIGN LINKAGE** (4236). It was
     * `void`, and the consequence was that `campaign_replies` had no honest way
     * to name the thread it belongs to — so `ThreadState::isCampaignReply()` was
     * `false` in production always and R20's context reached no screen. The
     * caller writes this onto the linkage row later in the same request, which
     * is the one moment both facts are in hand.
     *
     * ⛔ **AND IT NOW CARRIES THE ROW ID, WHICH IS WHAT TOOK A CUSTOMER'S WORDS
     * OFF A QUEUE PAYLOAD** (8720-8723). It answered with the `Conversation`
     * alone and discarded the `Message` {@see ConversationThreads::recordInbound()}
     * had just returned, so the one caller had no way to name the row and passed
     * the **text** down to `AgentTurns` and on into `AnswerAgentTurnJob`'s
     * constructor — where it was serialised whole into `jobs.payload`, and into
     * `failed_jobs.payload` on the third attempt. Both are tables with no
     * row-level security that no erasure reaches; the row it was copied from is
     * under RLS and cascade-deleted with its tenant. See
     * {@see FiledInboundMessage}.
     *
     * ⛔ **AND IT STAYS A REFUSAL RATHER THAN BECOMING A CREATION.** A caller
     * that wants a thread to hang a linkage on must not be able to make one
     * appear: every `return null` below is a rule this class exists to hold — no
     * tenant on a shared pool number, no contact on the tenant's own list — and
     * the linkage is simply recorded without a thread when one fires.
     *
     * @param  string  $from  The sender's number as the carrier gave it.
     *                        Normalised here and never stored in clear by this
     *                        class — the contact's own row is what holds it.
     */
    public function thread(
        string $from,
        ?string $toNumber,
        ?string $text,
        string $providerMessageId,
    ): ?FiledInboundMessage {
        $body = $text === null ? '' : trim($text);

        if ($body === '' || $toNumber === null) {
            return null;
        }

        $businessId = $this->numbers->tenantFor($toNumber);

        if ($businessId === null) {
            // The shared Lane A pool number, or a number no longer ours. See
            // the class docblock: this is the common case today and it is a
            // provisioning gap rather than a silent drop.
            return null;
        }

        $normalised = Identifier::normalise($from, OutreachChannel::Sms);

        if ($normalised === null) {
            return null;
        }

        try {
            return Tenancy::actingAs($businessId, function () use ($normalised, $body): ?FiledInboundMessage {
                $customer = Customer::query()->where('phone', $normalised)->first();

                if (! $customer instanceof Customer) {
                    return null;
                }

                $thread = $this->threads->openFor($customer);

                $filed = $this->threads->recordInbound($thread, $body);

                return new FiledInboundMessage($thread, (int) $filed->getKey(), $body);
            });
        } catch (Throwable $e) {
            // ⚠️ **THE CLASS NAME AND THE CARRIER'S HANDLE, NEVER THE NUMBER
            // AND NEVER THE BODY.** The two things this path holds are a member
            // of the public's mobile and the words they wrote; a failure to
            // file them must not pay for itself with either.
            Log::warning('An inbound message was recorded but could not be threaded for the tenant.', [
                'reason' => $e::class,
                'business_id' => $businessId,
                'provider_message_id' => $providerMessageId,
            ]);

            // ⛔ **A SWALLOWED FAILURE ANSWERS "NO THREAD", NEVER A PARTIAL
            // ONE.** `openFor()` may have committed before `recordInbound()`
            // threw, so a thread can exist that this call never returns — and
            // the linkage is then written without one. That is the right way
            // round: an unattached linkage is a true record, whereas returning
            // a thread whose message is missing would file the context against
            // a conversation that does not contain the reply it describes.
            return null;
        }
    }
}
