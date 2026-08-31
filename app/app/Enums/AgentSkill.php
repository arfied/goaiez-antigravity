<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Agent\AgentSkills;

/**
 * The sixteen things the front desk can do — T176 §2.2, patch P4.
 *
 * One case per row of the spec's own table, in its order, with the row's
 * grounding attached. R11 calls the agent *"a full front desk"* and R13 decides
 * which parts of that desk exist for a given business.
 *
 * ## ⛔ THE ORDER AND THE COUNT ARE THE SPEC'S, AND A LINT HOLDS THEM
 *
 * `tests/Feature/Architecture/AgentTest.php` parses §2.2's table out of the
 * shipped `docs/incoming-2026-08-15` pack and compares it against these cases.
 * That is 256's shape avoided rather than repeated: a lint written against a
 * hand-copied list of sixteen strings passes for ever, including the day
 * somebody drops row 12. It fails today if a row is added, removed or reordered
 * in the document nobody re-reads.
 *
 * ## R13 in one sentence, and where it is actually decided
 *
 * *"Each agent skill lights up only when its grounding exists … missing grounding
 * = the skill is absent and the agent falls back to capture + owner handoff. The
 * agent never invents a price, an appointment time, an arrival window, or a
 * link."*
 *
 * ⛔ **NOTHING ON THIS ENUM ANSWERS WHETHER A SKILL IS AVAILABLE**, and the
 * omission is the design. {@see AgentSkills} is the one resolver, because the
 * answer needs a tenant and a thread; an enum that could answer would be an enum
 * holding a database connection, and the second copy of that logic is how a
 * capability drifts from the fact behind it. What lives here is the *declaration*
 * — what each skill needs — which is a fact about the spec and not about a
 * business.
 *
 * ⚠️ **"ABSENT" IS STRONGER THAN "REFUSED", AND {@see AgentSkills} IMPLEMENTS THE
 * STRONGER ONE.** A dark skill is never named in the prompt at all, rather than
 * named with an instruction not to use it. Telling a model *"you can book
 * appointments but you have no link"* is the state R13 exists to prevent: it is
 * exactly where a plausible URL gets invented. P19's link-invention eval is the
 * test of it.
 */
enum AgentSkill: string
{
    /** 1 — any service question, answered from the knowledge store; never guessed. */
    case CustomerQuestions = 'customer_questions';

    /** 2 — "are you open", "where are you": answer plus a maps link. */
    case HoursAndDirections = 'hours_and_directions';

    /** 3 — new-work interest: qualify what · where · when · name, log the lead. */
    case SalesCapture = 'sales_capture';

    /**
     * 4 — "how much for…": list items only, with the tenant's disclaimer.
     *
     * ⛔ **OFF-LIST IS A CALLBACK, NEVER AN ESTIMATE.** `PriceList::quote()`
     * returns `null` for anything not priced and searches nothing approximately —
     * a price that is nearly right is the invention R13 forbids wearing a
     * plausible label.
     */
    case Quotes = 'quotes';

    /** 5 — "can I book": send the booking link, or capture times and hand off. */
    case BookAppointment = 'book_appointment';

    /**
     * 6 — the locksmith case: state the fee, send both links, record paid-unverified.
     *
     * ⛔ **THE PLATFORM NEVER ASSERTS A PAYMENT HAPPENED** (R12). A customer
     * saying they paid is *the customer's word*, stored as unverified, and the
     * owner checks their own system. There is no gateway on this path and there
     * is not meant to be one.
     */
    case CallOutFee = 'call_out_fee';

    /** 7 — "can you send…": the matching shared document, short-linked. */
    case SendDocuments = 'send_documents';

    /**
     * 8 — "just tell them…", or nothing else fits: a structured message.
     *
     * ⚠️ **THIS IS THE FLOOR THE WHOLE OF R13 FALLS BACK TO.** *"Missing grounding
     * = the skill is absent and the agent falls back to capture + owner
     * handoff"*, and this is that handoff. It is `AlwaysOn` for that reason, not
     * because it happens to need nothing.
     */
    case MessageTaking = 'message_taking';

    /** 9 — tenant-defined urgent terms: page the owner, give the emergency line. */
    case UrgentEscalation = 'urgent_escalation';

    /** 10 — "move / cancel my appointment": capture, notify, re-send the link if set. */
    case RescheduleOrCancel = 'reschedule_or_cancel';

    /**
     * 11 — a CRM phone match: greet by name, use history for context.
     *
     * ⛔ **NEVER ANOTHER CONTACT'S DATA.** §2.2's own words. The match is to the
     * number that texted in and to nothing else.
     */
    case ExistingCustomerContext = 'existing_customer_context';

    /** 12 — inbound MMS: store, acknowledge, forward. No vision analysis at SL. */
    case PhotoIntake = 'photo_intake';

    /** 13 — a satisfied customer on a resolved thread: offer the review link once. */
    case ReviewAsk = 'review_ask';

    /** 14 — a link sent and no reply: one polite nudge inside 24h, then stop. */
    case SilentCustomerNudge = 'silent_customer_nudge';

    /** 15 — clearly misdirected or abusive: close politely, flag, no escalation loop. */
    case WrongNumberOrSpam = 'wrong_number_or_spam';

    /** 16 — a reply to a review invite or reactivation send, carrying campaign context (R20). */
    case CampaignReply = 'campaign_reply';

    /**
     * The row number in §2.2's table.
     *
     * Carried so the lint can compare position as well as membership, and so an
     * operator screen can show the spec's own numbering rather than an ordinal
     * derived from `cases()` that would silently renumber on an insertion.
     */
    public function specRow(): int
    {
        return match ($this) {
            self::CustomerQuestions => 1,
            self::HoursAndDirections => 2,
            self::SalesCapture => 3,
            self::Quotes => 4,
            self::BookAppointment => 5,
            self::CallOutFee => 6,
            self::SendDocuments => 7,
            self::MessageTaking => 8,
            self::UrgentEscalation => 9,
            self::RescheduleOrCancel => 10,
            self::ExistingCustomerContext => 11,
            self::PhotoIntake => 12,
            self::ReviewAsk => 13,
            self::SilentCustomerNudge => 14,
            self::WrongNumberOrSpam => 15,
            self::CampaignReply => 16,
        };
    }

    /**
     * The fact this skill needs before it may exist (R13).
     */
    public function grounding(): AgentGroundingSource
    {
        return match ($this) {
            self::SalesCapture,
            self::MessageTaking,
            // ⚠️ **10 IS `AlwaysOn` AND THAT IS THE SPEC'S READING, NOT A
            // RELAXATION.** Its grounding column says *"booking link if set"* —
            // the *capture and notify* half runs for everybody and only the
            // re-send half needs the link. Gating the whole row on the link
            // would leave a business with no scheduler unable to hear that
            // somebody wants to cancel, which is worse than the state R13 is
            // protecting against.
            self::RescheduleOrCancel,
            self::WrongNumberOrSpam => AgentGroundingSource::AlwaysOn,

            self::CustomerQuestions => AgentGroundingSource::KnowledgeChunks,
            self::HoursAndDirections => AgentGroundingSource::PlacesData,
            self::Quotes => AgentGroundingSource::PriceList,
            self::BookAppointment => AgentGroundingSource::BookingLink,
            self::CallOutFee => AgentGroundingSource::PaymentLinkWithFee,
            self::SendDocuments => AgentGroundingSource::SharedDocuments,
            self::UrgentEscalation => AgentGroundingSource::UrgentTerms,
            self::ExistingCustomerContext => AgentGroundingSource::CrmMatch,
            self::PhotoIntake => AgentGroundingSource::InboundMedia,
            self::CampaignReply => AgentGroundingSource::CampaignContext,

            // ⛔ **13 IS GROUNDED ON A REAL LINK AS OF P12, WHICH DEPARTS FROM
            // §2.2's RIGHT-HAND COLUMN AND IS ARGUED IN
            // {@see AgentGroundingSource::ReviewAskOffer}.** P4 read the column
            // literally (*"toggle, default ON"*) and the result was a prompt line
            // telling the model it may offer a link beside standing instructions
            // saying never to invent one, with nothing supplying the link. The
            // toggle is unchanged and still and-ed on top — see {@see self::toggle()}.
            self::ReviewAsk => AgentGroundingSource::ReviewAskOffer,

            // ⚠️ **14 IS STILL `AlwaysOn` GROUNDED AND TOGGLE GATED**, which is
            // §2.2's own column, and P12's argument does not reach it: the nudge
            // carries no link of its own. It is one short sentence asking whether
            // somebody still needs help, and P11 sends it from a queued job
            // rather than from a model turn, so there is nothing here to ground.
            self::SilentCustomerNudge => AgentGroundingSource::AlwaysOn,
        };
    }

    /**
     * The §2.4 switch a business may use to stop this skill, if it has one.
     *
     * ⚠️ **AND-ED WITH THE GROUNDING, NEVER INSTEAD OF IT.** Skill 4 carries both:
     * a price list it may quote from *and* permission to quote at all.
     */
    public function toggle(): ?AssistantToggle
    {
        return match ($this) {
            self::Quotes => AssistantToggle::Quotes,
            self::ReviewAsk => AssistantToggle::ReviewAsk,
            self::SilentCustomerNudge => AssistantToggle::Nudge,
            default => null,
        };
    }

    /**
     * Whether this skill survives a business that skipped the wizard entirely.
     *
     * §2.4: *"Skipped = the agent runs skills 1–3, 8–12, 15–16 (capture-and-handoff
     * posture) — R13 does the rest."*
     *
     * ⚠️ **DERIVED FROM THE SPEC'S LIST RATHER THAN FROM {@see self::grounding()},
     * AND THE DIFFERENCE IS DELIBERATE.** Rows 1, 2, 11 and 12 are in the
     * skipped-wizard set and are *not* `AlwaysOn`: their groundings — uploaded
     * answers, a Google listing, a CRM match, an inbound photo — do not come from
     * the wizard at all. Computing this from the grounding would drop those four
     * and quietly contradict §2.4 while every test still passed, which is why the
     * two are written out separately and a test asserts the spec's own span.
     */
    public function survivesASkippedWizard(): bool
    {
        $row = $this->specRow();

        return ($row >= 1 && $row <= 3)
            || ($row >= 8 && $row <= 12)
            || ($row >= 15 && $row <= 16);
    }

    /**
     * The one line the model is told about this skill when it is lit.
     *
     * ⚠️ **CAPABILITIES, NOT PROHIBITIONS.** Rail 2 is knowledge-bounding and
     * rail 5 is the refusal set; this is neither. A dark skill contributes
     * *nothing* here — no "you cannot book" line — because naming an absent
     * capability is what R13's own sentence warns about.
     *
     * ⚠️ **AND THE TEXT IS OURS, WHICH IS WHY IT NEEDS NO FENCE.** Every string
     * below is written here and reviewed here. The untrusted values — the
     * customer's message, the business's own labels and disclaimer — are fenced
     * where they enter the prompt, by `AgentComposer`, and never by this method.
     */
    public function promptLine(): string
    {
        return match ($this) {
            self::CustomerQuestions => 'Answer service questions using only the business notes given to you. '
                .'If the notes do not answer it, say you will get the owner to confirm.',
            // ⛔ **DIRECTIONS ONLY, AND THE ROW'S NAME PROMISES MORE THAN THIS
            // SCHEMA HOLDS.** `locations` carries an address and a maps URL and
            // **no opening-hours column at all**, so there is nothing behind
            // *"are you open"*. Writing the line as though there were is exactly
            // R13's failure mode with a different noun — a model told it can
            // answer about hours will answer about hours. See
            // `AgentSkills::hasPlacesData()`.
            self::HoursAndDirections => 'Answer questions about where the business is and how to get there, '
                .'using the address given to you. You have not been given opening hours: if somebody asks '
                .'when you are open, say you will get that confirmed rather than guessing.',
            self::SalesCapture => 'When somebody wants work done, find out what, where, when, and their name. '
                .'Then steer them to booking or a quote.',
            self::Quotes => 'Give a price only for a job that appears in the price list given to you, and '
                .'always say the disclaimer line with it. For anything not on the list, offer a callback '
                .'instead of a figure.',
            // ⚠️ **"EXACTLY AS YOU WERE GIVEN IT" IS THE ONLY CONTROL OVER A
            // RETYPED TOKEN** (4271). The link the model is handed is a short
            // link whose base62 token is case-sensitive, and the outbound lint
            // compares links case-insensitively — so a tidied capital is a link
            // that passes the rail and resolves to nothing. See
            // `AgentComposer::facts()`.
            self::BookAppointment => 'When somebody wants to book, send them the booking link exactly as '
                .'you were given it.',
            self::CallOutFee => 'If somebody wants a visit, state the call-out fee and what it covers, send '
                .'the payment link and the booking link together, and ask them to reply once they have paid.',
            self::SendDocuments => 'When somebody asks for a document you have, send the matching one.',
            self::MessageTaking => 'If nothing else fits, take a message: their name, the best number, and '
                .'what it is about. Tell them the owner will come back to them.',
            self::UrgentEscalation => 'If the message contains one of the urgent words listed for this '
                .'business, treat it as urgent, tell them help is being arranged, and give the emergency '
                .'line if one is listed.',
            self::RescheduleOrCancel => 'If somebody wants to move or cancel an appointment, take the '
                .'details and tell them the owner will confirm.',
            self::ExistingCustomerContext => 'This is an existing customer. Greet them by name and use what '
                .'you have been told about them. Never mention any other customer.',
            self::PhotoIntake => 'They sent a photo. Say you have it and that the owner will look at it. '
                .'Do not describe or interpret it.',
            // ⛔ **"THE REVIEW LINK YOU WERE GIVEN", AND THE WORDING IS P12's
            // POINT.** The link is now in the facts block whenever this line is
            // present, so the sentence can point at something. Before P12 it
            // pointed at nothing, which is where a plausible URL comes from.
            // ⚠️ **AND IT NAMES NO RATING, DELIBERATELY** — asking for a
            // particular one is soliciting a review's content, which is the one
            // thing no part of this product does.
            self::ReviewAsk => 'If they are clearly happy and the matter is settled, you may once invite '
                .'them to leave feedback, using the review link you were given, copied exactly. Never '
                .'offer it twice, never use any other address, and never ask for a particular rating '
                .'or suggest what they should say.',
            self::SilentCustomerNudge => 'If a link was sent and nobody replied, one short polite follow-up '
                .'is allowed, and only one.',
            self::WrongNumberOrSpam => 'If the message is clearly misdirected or abusive, close it off '
                .'politely in one line and stop.',
            self::CampaignReply => 'They are replying to a message this business sent them. Use that '
                .'context. If they ask who this is, say plainly and remind them they can reply STOP.',
        };
    }

    /**
     * What an owner is told this skill does, in their words.
     */
    public function ownerLabel(): string
    {
        return match ($this) {
            self::CustomerQuestions => 'Answers questions about your business',
            self::HoursAndDirections => 'Gives your hours and directions',
            self::SalesCapture => 'Captures new enquiries',
            self::Quotes => 'Quotes your listed prices',
            self::BookAppointment => 'Sends your booking link',
            self::CallOutFee => 'Collects your call-out fee',
            self::SendDocuments => 'Sends the documents you share',
            self::MessageTaking => 'Takes a message for you',
            self::UrgentEscalation => 'Pages you when something is urgent',
            self::RescheduleOrCancel => 'Handles changes and cancellations',
            self::ExistingCustomerContext => 'Recognises your existing customers',
            self::PhotoIntake => 'Accepts photos',
            self::ReviewAsk => 'Asks happy customers for a review',
            self::SilentCustomerNudge => 'Follows up once if nobody replies',
            self::WrongNumberOrSpam => 'Closes off wrong numbers',
            self::CampaignReply => 'Answers replies to your campaigns',
        };
    }
}
