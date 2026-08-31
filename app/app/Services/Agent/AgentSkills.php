<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Contracts\Links\LinkRegistry;
use App\Enums\AgentGroundingSource;
use App\Enums\AgentSkill;
use App\Enums\AssistantToggle;
use App\Enums\TenantLinkKind;
use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\Location;
use App\Services\Assistant\AssistantToggles;
use App\Services\Assistant\PriceBook;
use App\Services\Assistant\UrgentTerms;
use App\Support\Tenancy;

/**
 * The one place R13 is decided — T176's capability-gating law, patch P4.
 *
 * *"Each agent skill lights up only when its grounding exists: price list → quote
 * · booking URL → book · fee amount + payment URL → fee · shared documents →
 * docs. Missing grounding = the skill is absent and the agent falls back to
 * capture + owner handoff. The agent never invents a price, an appointment time,
 * an arrival window, or a link."*
 *
 * ## ⛔ ONE RESOLVER, AND THE REASON IS NOT TIDINESS
 *
 * Every grounding question here is *"does this business have X"*, and each one is
 * already answered authoritatively somewhere else — `PriceBook::groundsQuoting()`,
 * `TenantLink::groundsFeeCollection()`, `UrgentTerms::groundsEscalation()`. This
 * class asks those and does not re-implement one of them. A second opinion about
 * whether a price list exists is how the assistant comes to be **told it can
 * quote** while holding nothing to quote from, and a model handed that
 * combination invents a figure. `LinkRegistry`'s own contract says it in as many
 * words: *"the failure this prevents is not the agent erroring — it is the agent
 * being told it can book while holding nothing to book with."*
 *
 * ## ⚠️ A TOGGLE AND A GROUNDING ARE AND-ED, AND THE REASONS STAY APART
 *
 * Skill 4 needs a reviewed price list **and** the quotes switch. Both off looks
 * identical in the prompt and completely different on the owner's screen — *"you
 * turned this off"* and *"you have not written a price list"* have different
 * remedies — so {@see AgentSkillSet::unavailable()} carries the reason rather
 * than a bare list of what is dark.
 *
 * ## ⚠️ IT TAKES A CONVERSATION, BECAUSE THREE GROUNDINGS ARE PER-THREAD
 *
 * A CRM match, an inbound photo and a campaign origin are true of one thread and
 * false of the next ({@see AgentGroundingSource::isPerThread()}). A set cached
 * per tenant would light skill 11 on a stranger's first text and greet them by
 * somebody else's name.
 *
 * ## ⛔ THREE GROUNDINGS ARE PARTLY OR WHOLLY UNBUILT, AND THAT IS SAID HERE
 *
 * Recorded rather than left to be discovered from a flat screen, which is 272's
 * own instruction:
 *
 *  - **`InboundMedia` has no writer** — P10 is unbuilt, nothing in `app/` records
 *    media on an inbound message, so skill 12 is dark for every thread today. It
 *    is a parameter on {@see self::forThread()} rather than a lookup precisely so
 *    that P10 supplies it rather than this class guessing.
 *  - **`CampaignContext` is always `null`** — `AgentThreadStates::stateOf()` says
 *    so and says why (*"campaign context is P20's half and is not invented
 *    here"*), so skill 16 is dark for every thread today.
 *  - **`PlacesData` grounds directions and NOT hours** — see
 *    {@see self::hasPlacesData()}. `locations` has an address and a maps URL and
 *    **no opening-hours column at all**, so §2.2's *"are you open"* half has
 *    nothing behind it in this schema. ⛔ **AND UNTIL 2026-08-20 IT GROUNDED
 *    NEITHER, BECAUSE `locations.address` HAD NO WRITER IN `app/`** (6100,
 *    6104): `LocationFactory` filled it and nothing else did, so this skill was
 *    lit in every test and **dark for every tenant in production**, which is
 *    272's shape sitting inside the list this class keeps of 272's instances.
 *    `App\Services\Tenant\LocationDetails` is the writer.
 *    ⛔ **The hours half is unchanged and must stay refused** — the prompt line
 *    says so out loud, and lighting this skill on an address while letting the
 *    model be *told* it can answer about hours is R13's own failure mode with a
 *    different noun.
 */
final class AgentSkills
{
    public function __construct(
        private readonly PriceBook $prices,
        private readonly UrgentTerms $urgent,
        private readonly LinkRegistry $links,
        private readonly AssistantToggles $toggles,
        private readonly ReviewAskBridge $reviewAsk,
    ) {}

    /**
     * Which skills exist for the next turn on this thread.
     *
     * @param  bool  $hasInboundMedia  Whether the message being answered carried a
     *                                 photo. **P10's to supply** — see the class
     *                                 docblock. Defaulted to `false` rather than
     *                                 looked up, because a lookup against a table
     *                                 nothing writes would return `false` while
     *                                 reading as though it had asked.
     */
    public function forThread(Conversation $conversation, bool $hasInboundMedia = false): AgentSkillSet
    {
        Tenancy::idOrFail();

        $grounded = $this->groundings($conversation, $hasInboundMedia);

        $lit = [];
        $dark = [];

        foreach (AgentSkill::cases() as $skill) {
            $reason = $this->whyDark($skill, $grounded);

            if ($reason === null) {
                $lit[] = $skill;

                continue;
            }

            $dark[$skill->value] = $reason;
        }

        return AgentSkillSet::of($lit, $dark);
    }

    /**
     * Why this skill is off, or `null` when it is on.
     *
     * ⚠️ **THE GROUNDING IS ASKED FIRST AND THE TOGGLE SECOND, AND THE ORDER IS
     * THE OWNER'S RATHER THAN THE MACHINE'S.** Both refuse the same way, so
     * swapping them changes no behaviour — a mutation run confirms the suite
     * stays green either way, and that is recorded rather than dressed up as a
     * safety property. What it changes is the sentence a business reads when both
     * are true: *"add a price list"* is the actionable one, and telling somebody
     * to flip a switch that would still leave the skill dark is a support ticket.
     *
     * @param  array<value-of<AgentGroundingSource>, bool>  $grounded
     */
    private function whyDark(AgentSkill $skill, array $grounded): ?string
    {
        $source = $skill->grounding();

        if (($grounded[$source->value] ?? false) === false) {
            return 'Needs '.$source->ownerLabel().'.';
        }

        $toggle = $skill->toggle();

        if ($toggle instanceof AssistantToggle && ! $this->toggles->isEnabled($toggle)) {
            return 'You switched this off.';
        }

        return null;
    }

    /**
     * Every grounding question, asked once.
     *
     * ⚠️ **ONCE PER TURN, NOT ONCE PER SKILL.** Two skills share no grounding
     * today, so the saving is not the point — the point is that a per-skill read
     * would let two skills see different answers to the same question if a write
     * landed between them, and the pair that would diverge is skill 5 and skill
     * 10, whose whole relationship is that one re-sends what the other sends.
     *
     * @return array<value-of<AgentGroundingSource>, bool>
     */
    private function groundings(Conversation $conversation, bool $hasInboundMedia): array
    {
        $linkGrounding = $this->links->grounded();

        return [
            AgentGroundingSource::AlwaysOn->value => true,

            AgentGroundingSource::KnowledgeChunks->value => $this->hasKnowledge(),
            AgentGroundingSource::PlacesData->value => $this->hasPlacesData($conversation),
            AgentGroundingSource::PriceList->value => $this->prices->groundsQuoting(),

            // ⛔ **P6's OWN ANSWER, NOT A SECOND READ OF `tenant_links`.**
            // `TenantLinks::grounded()` already ands the fee into the payment
            // key — *"a payment link with no fee is still returned by payment()
            // … but it does not ground the skill that quotes a call-out
            // charge"* — and re-deriving that here is how the two come to
            // disagree about skill 6.
            AgentGroundingSource::BookingLink->value => $linkGrounding[TenantLinkKind::Booking->value] ?? false,
            AgentGroundingSource::PaymentLinkWithFee->value => $linkGrounding[TenantLinkKind::Payment->value] ?? false,
            AgentGroundingSource::SharedDocuments->value => $linkGrounding[TenantLinkKind::Document->value] ?? false,

            AgentGroundingSource::UrgentTerms->value => $this->urgent->groundsEscalation(),

            // A thread with a contact on it. Null is the ordinary answer — a
            // missed call from somebody who is not a contact yet.
            AgentGroundingSource::CrmMatch->value => $conversation->customer_id !== null,

            // ⛔ **P10's, AND `false` FOR EVERY THREAD TODAY.** See the class
            // docblock: nothing in `app/` writes media on an inbound message.
            AgentGroundingSource::InboundMedia->value => $hasInboundMedia,

            // ⛔ **P20's, AND `false` FOR EVERY THREAD TODAY.** `ThreadState`'s
            // campaign half is hardcoded `null` by P3, deliberately — a guess
            // would be indistinguishable from the truth, since `null` is the
            // common and correct answer for a missed-call thread.
            AgentGroundingSource::CampaignContext->value => $this->campaignContext($conversation),

            // ⛔ **P12's, AND IT IS THE ONE GROUNDING THAT ANSWERS "HAS THIS
            // PERSON ALREADY BEEN ASKED".** `groundedFor()` mints nothing — see
            // its docblock, because a grounding check with a side effect would
            // leave a dead short link behind on every turn of every thread.
            AgentGroundingSource::ReviewAskOffer->value => $this->reviewAsk->groundedFor($conversation),
        ];
    }

    /**
     * Whether this business has uploaded anything the assistant can answer from.
     *
     * ⚠️ **AN `exists()` RATHER THAN A RETRIEVAL, AND THE DIFFERENCE IS AN LLM
     * CALL.** `KnowledgeRetriever::retrieve()` embeds the question before it can
     * compare it, which `29` §2 forbids on any synchronous path and which this
     * method is asked on every turn. *Does a corpus exist* and *is anything in it
     * relevant* are two questions: this is the first, and `AgentGrounding` — on
     * the queue, where it belongs — is the second.
     *
     * ⚠️ **SCOPED BY `KnowledgeChunk`'s GLOBAL SCOPE, NOT BY A HAND-WRITTEN
     * PREDICATE.** The one place this application writes `business_id` into
     * retrieval SQL by hand is `nearestTo()`, and `TenancyTest` watches it. A
     * second hand-written predicate here would be a second thing to get wrong.
     */
    private function hasKnowledge(): bool
    {
        return KnowledgeChunk::query()->exists();
    }

    /**
     * Whether there is listing detail to answer from.
     *
     * ⛔ **THIS GROUNDS DIRECTIONS AND DOES NOT GROUND HOURS, AND §2.2 ASKS FOR
     * BOTH.** `locations` carries `address`, `google_maps_url`, `google_place_id`
     * and `timezone`, and **no opening-hours column of any kind** — so *"are you
     * open"* has nothing behind it in this schema. Recorded rather than papered
     * over: the address is a real grounding for the directions half, and
     * {@see AgentSkill::HoursAndDirections}'s prompt line is written to claim only
     * that. Lighting the skill on an address and letting it be *told* it can
     * answer about hours would be R13's own failure mode with a different noun.
     *
     * ⚠️ **THE LOCATION IS THE THREAD'S WHERE THERE IS ONE.** A business with
     * three shops has three addresses, and answering with the wrong one is worse
     * than not answering. Where the thread names no location, the tenant's single
     * location is used and a business with several is refused —
     * {@see self::addressFor()}, which is also what supplies the address to the
     * prompt, so the permission and the fact cannot disagree.
     */
    private function hasPlacesData(Conversation $conversation): bool
    {
        return $this->addressFor($conversation) !== null;
    }

    /**
     * The address this thread may be answered with, or null.
     *
     * ⛔ **PUBLIC BECAUSE `AgentComposer::facts()` HAS TO PUT THE SAME ADDRESS IN
     * FRONT OF THE MODEL, AND A SECOND RESOLUTION WOULD BE A SECOND ANSWER**
     * (6104). {@see AgentSkill::HoursAndDirections}'s prompt line says *"using
     * the address given to you"*, so the fact and the permission have to come
     * from one place — this class's own opening argument, that *"a second opinion
     * about whether a price list exists is how the assistant comes to be told it
     * can quote while holding nothing to quote from"*. `PriceBook` is the
     * precedent: `groundsQuoting()` lights the skill and `list()` supplies the
     * figures, one authority answering both.
     *
     * ⛔ **AND THE DEFECT THIS CLOSES WAS ALREADY LIVE, NOT HYPOTHETICAL.** Until
     * 2026-08-20 nothing ever put an address in the facts block, so skill 2's
     * prompt line pointed at nothing — P19's booking-link finding (4187) exactly,
     * on a second skill, in the same method, with the write-up of the first one
     * ten lines above it. It was invisible because `locations.address` had no
     * writer, so the skill was never lit for a real tenant; **giving the column a
     * writer is what would have made it fire**, and a model told it can give an
     * address and handed none invents one.
     *
     * ⚠️ **THE LOCATION IS THE THREAD'S WHERE THERE IS ONE**, and a
     * multi-location business whose thread names none is refused rather than
     * guessed at — answering confidently with one of three addresses is worse
     * than not answering, and the customer has no way to tell it is the wrong
     * shop. One location is unambiguous; more than one, with the thread not
     * saying which, is a question this skill cannot answer and the message-taking
     * floor can.
     *
     * ⚠️ **NO `address_confirmed_at` PREDICATE.** An address with no confirmation
     * is unrepresentable — `locations_address_and_confirmation_travel_together`
     * says so at the database — so asking here would be an inner guard the outer
     * one makes unfalsifiable (398).
     */
    public function addressFor(Conversation $conversation): ?string
    {
        $locationId = $conversation->location_id;

        if ($locationId !== null) {
            $address = Location::query()->whereKey($locationId)->value('address');

            return is_string($address) ? $address : null;
        }

        if (Location::query()->count() !== 1) {
            return null;
        }

        $address = Location::query()->value('address');

        return is_string($address) ? $address : null;
    }

    /**
     * Whether this thread began as a reply to a campaign send (R20).
     *
     * ⚠️ **ASKED THROUGH `ThreadState` RATHER THAN OFF A COLUMN**, so that when
     * P20 fills the campaign half in, this lights up with no change here. Reading
     * a column directly would be a second definition of *"is this a campaign
     * reply"* for P20 to disagree with.
     */
    private function campaignContext(Conversation $conversation): bool
    {
        return app(AgentThreadStates::class)->stateFor($conversation)->isCampaignReply();
    }
}
