<?php

declare(strict_types=1);

namespace Tests\Support\AgentEvals;

/**
 * The nine things P19 says the eval suite covers, and one this application added.
 *
 * ⚠️ **THE COUNT IN THIS SENTENCE SAID "NINE" UNTIL 2026-08-20** — see
 * {@see self::ContactInvention}, which is 6107's and not the spec's.
 *
 * *"the new tier enters the eval harness — prompt-injection suite · price-invention
 * (off-list) refusals · link-invention refusals · payment-claim refusals (R12) ·
 * disclosure presence · takeover-latch respect · turn-cap fire · model-down
 * fallback · campaign-context correctness (R20). Sandbox-gated like every tier;
 * suite red = tier does not ship."*
 *
 * ## ⛔ WHY THIS IS AN ENUM RATHER THAN A COMMENT
 *
 * A checklist in a docblock is a checklist nobody re-reads. `AgentEvalTest`'s own
 * coverage lint walks {@see self::cases()} and fails the build on any subject with
 * **no case and no named test** — so a row of the spec cannot be quietly dropped,
 * and a subject cannot be marked covered by a test that has since been renamed or
 * deleted. That is 256 applied to the gate itself: a suite that covers eight of
 * nine subjects passes every one of its own assertions.
 *
 * ⚠️ **NOT A DATABASE ENUM AND NOT IN `app/Enums`**, because nothing persists it.
 * `CLAUDE.md`'s rule is about columns; this never leaves the test run.
 */
enum AgentEvalSubject: string
{
    case PromptInjection = 'prompt_injection';

    case PriceInvention = 'price_invention';

    case LinkInvention = 'link_invention';

    case PaymentClaim = 'payment_claim';

    case Disclosure = 'disclosure';

    case TakeoverLatch = 'takeover_latch';

    case TurnCap = 'turn_cap';

    case ModelDown = 'model_down';

    case CampaignContext = 'campaign_context';

    /**
     * ⛔ **THE ONE SUBJECT P19 DOES NOT NAME, ADDED BY 6107's SLICE AND SAID SO
     * HERE.** The row above quotes nine and this is a tenth; it is registered
     * rather than left out because `AgentEvalTest`'s limb lint requires an eval
     * case per limb of rail 5, and the address and phone limbs are two new ones.
     * ⚠️ **Widening a spec-derived enum with something the spec did not say is
     * exactly how a lint stops meaning what its docblock claims** (511), so the
     * departure is named at the case rather than folded into the count above.
     */
    case ContactInvention = 'contact_invention';

    /**
     * Whether this subject's oracle is the text of the reply.
     *
     * ⛔ **THE FOUR THAT ANSWER `false` ARE NOT SECOND-CLASS, THEY ARE A DIFFERENT
     * QUESTION.** {@see AgentEvalCase} asks *"was this output refused, and was a
     * good one not"*, which is the wrong shape for a latch — whose whole claim is
     * that **nothing happened at all**: no model call, no turn, no message. Bending
     * those into a case struct would mean inventing a violating "output" for a path
     * that produces none, and an eval named for a property it does not test is
     * worse than no eval (CLAUDE.md 352/397/565). They are written out longhand in
     * `AgentEvalTest` and registered by name instead.
     */
    public function isJudgedOnTheReplyText(): bool
    {
        return match ($this) {
            self::PromptInjection,
            self::PriceInvention,
            self::LinkInvention,
            self::PaymentClaim,
            self::ContactInvention,
            self::Disclosure => true,
            self::TakeoverLatch,
            self::TurnCap,
            self::ModelDown,
            self::CampaignContext => false,
        };
    }
}
