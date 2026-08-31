<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Agent\AgentComposer;
use App\Services\Agent\AgentSkills;

/**
 * The fact a skill needs before it may exist — T176 R13, patch P4.
 *
 * The capability-gating law names four groundings in its own sentence (*"price
 * list → quote · booking URL → book · fee amount + payment URL → fee · shared
 * documents → docs"*) and §2.2's table names five more in its right-hand column.
 * They are cases here rather than closures on {@see AgentSkill} for one reason:
 * **a skill must not be able to answer whether it is grounded.** The answer is a
 * database read against the tenant in context, and an enum that could perform it
 * would be an enum with a tenant.
 *
 * ⛔ **THE ONLY PLACE THAT TURNS ONE OF THESE INTO A YES OR A NO IS
 * {@see AgentSkills}**, which is what makes R13 a single decision rather than
 * nine scattered ones. A second resolver is a second reading of *"does this
 * business have a price list"* — and the failure that shape produces is the
 * assistant being **told it can quote** while holding nothing to quote from,
 * which is precisely the state a model invents a price in.
 *
 * ⚠️ **`AlwaysOn` IS A REAL CASE AND NOT AN ABSENCE.** Nine skills survive a
 * business that has filled in nothing (§2.4: *"skipped = the agent runs skills
 * 1–3, 8–12, 15–16"*), and spelling that as `null` would make "ungrounded" and
 * "needs no grounding" the same value. They are opposite answers: one switches a
 * skill off and the other switches it on unconditionally.
 */
enum AgentGroundingSource: string
{
    /**
     * Nothing is needed. Capture, message-taking and closing a wrong number work
     * for a business that has told us nothing at all, and are the posture R13
     * falls back **to** rather than skills that survive by accident.
     */
    case AlwaysOn = 'always_on';

    /**
     * The tenant's own ingested documents — `knowledge_chunks`, reached through
     * `KnowledgeRetriever` and never by a second similarity query.
     */
    case KnowledgeChunks = 'knowledge_chunks';

    /**
     * The Google Places record behind the location: hours, address, maps link.
     */
    case PlacesData = 'places_data';

    /**
     * A confirmed price list — `PriceBook::groundsQuoting()`, which counts only
     * rows a person has reviewed.
     */
    case PriceList = 'price_list';

    /**
     * A booking URL the business set (P6, `TenantLinkKind::Booking`).
     */
    case BookingLink = 'booking_link';

    /**
     * A payment URL **and** a fee to name.
     *
     * ⚠️ **BOTH, AND THE LINK ALONE IS NOT ENOUGH** — R13 spells the grounding as
     * *"fee amount + payment URL"*, and `TenantLink::groundsFeeCollection()` is
     * where that pair is judged. A business with a payment page and no fee can
     * still be sent somewhere to pay; what it cannot do is have its assistant
     * quote a call-out charge nobody set.
     */
    case PaymentLinkWithFee = 'payment_link_with_fee';

    /**
     * At least one named shared document (P6, `TenantLinkKind::Document`).
     */
    case SharedDocuments = 'shared_documents';

    /**
     * The tenant's urgent-terms list (P5, `UrgentTerms::groundsEscalation()`).
     *
     * ⛔ **AN EMPTY LIST SWITCHES OFF THE TRIGGER, NEVER THE SAFETY.** `UrgentTerms`
     * says it in its own docblock and it is restated here because this is the enum
     * somebody reads while deciding what an absent list means: skill 9's
     * *"life-safety → advise emergency services"* is unconditional and lives in
     * {@see AgentComposer}'s standing instructions, outside
     * every gate on this page.
     */
    case UrgentTerms = 'urgent_terms';

    /**
     * A CRM contact matched to the number that texted in.
     */
    case CrmMatch = 'crm_match';

    /**
     * Media on the inbound message (P10).
     */
    case InboundMedia = 'inbound_media';

    /**
     * Campaign context on the thread — which send prompted this reply (R20, P20).
     */
    case CampaignContext = 'campaign_context';

    /**
     * A minted, permitted, not-yet-spent feedback-page link for this thread
     * (skill 13, P12 — `ReviewAskBridge::groundedFor()`).
     *
     * ⛔ **§2.2's GROUNDING COLUMN FOR ROW 13 SAYS ONLY "TOGGLE, DEFAULT ON", AND
     * THIS IS A DELIBERATE DEPARTURE FROM IT.** P4 encoded the column literally
     * and shipped row 13's prompt line — *"you may offer the review link once"* —
     * with **nothing anywhere that puts a link in front of the model**, while the
     * standing instructions say *"never invent a link … use only what you were
     * given"*. R13's own sentence is the authority the column is a summary of:
     * *"the agent never invents a price, an appointment time, an arrival window,
     * or **a link**"*. So the link is row 13's grounding in substance, and the
     * toggle stays and-ed on top of it exactly as the price list and the quotes
     * switch are for row 4. Both readings are written up in `DECISIONS.md`.
     *
     * ⚠️ **PER-THREAD, AND FOR THREE REASONS AT ONCE**: the contact differs, the
     * one-ask-per-thread record differs, and the invite ledger is a fact about a
     * person. A set cached per tenant would offer a second customer the link the
     * first one already had.
     */
    case ReviewAskOffer = 'review_ask_offer';

    /**
     * What an owner is told is missing, in their words rather than ours.
     *
     * ⚠️ **OUTCOME LANGUAGE, BECAUSE THIS REACHES A SCREEN** (`22`: *"every string
     * names what the person controls, never how the system is built"*). "No
     * `tenant_links` row of kind `booking`" is the truth and is useless to the
     * person who can fix it.
     */
    public function ownerLabel(): string
    {
        return match ($this) {
            self::AlwaysOn => 'always available',
            self::KnowledgeChunks => 'answers you have uploaded',
            self::PlacesData => 'your Google listing',
            self::PriceList => 'your price list',
            self::BookingLink => 'your booking link',
            self::PaymentLinkWithFee => 'your payment link and call-out fee',
            self::SharedDocuments => 'documents you share',
            self::UrgentTerms => 'your urgent words',
            self::CrmMatch => 'a matching customer record',
            self::InboundMedia => 'a photo on the message',
            self::CampaignContext => 'the message they replied to',
            // ⚠️ NAMES THE PAGE, NOT THE LINK TABLE. An owner can act on "your
            // feedback page"; "a minted short link" is our machinery.
            self::ReviewAskOffer => 'your feedback page, and a customer who has not been asked yet',
        };
    }

    /**
     * Whether this grounding is a fact about the **thread** rather than about the
     * business.
     *
     * ⚠️ **THE DISTINCTION IS WHY {@see AgentSkills} TAKES A CONVERSATION.** A
     * price list is the same for every thread a tenant has; a CRM match, an
     * inbound photo and a campaign origin are true of one thread and false of the
     * next, so a capability set cached per tenant would light skill 11 on a
     * stranger's first text.
     */
    public function isPerThread(): bool
    {
        return match ($this) {
            self::CrmMatch, self::InboundMedia, self::CampaignContext, self::ReviewAskOffer => true,
            self::AlwaysOn,
            self::KnowledgeChunks,
            self::PlacesData,
            self::PriceList,
            self::BookingLink,
            self::PaymentLinkWithFee,
            self::SharedDocuments,
            self::UrgentTerms => false,
        };
    }
}
