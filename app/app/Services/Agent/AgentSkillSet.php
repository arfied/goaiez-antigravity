<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\AgentGroundingSource;
use App\Enums\AgentSkill;

/**
 * Which parts of the front desk exist for this business, on this thread — T176
 * R13, patch P4.
 *
 * The answer {@see AgentSkills} computed, frozen. It is a separate object rather
 * than a bare array of skills because the *dark* half is as load-bearing as the
 * lit half and has two different audiences: the prompt must never hear about a
 * dark skill, and the owner's screen must hear about nothing else.
 *
 * ## ⛔ THE CAPABILITY BRIEFING NAMES ONLY WHAT IS LIT
 *
 * R13's own sentence is *"missing grounding = the skill is absent"*, and absent
 * is stronger than refused. {@see self::capabilityBriefing()} contains no
 * negative space at all — no *"you cannot book"*, no *"no price list is
 * configured"*. Telling a model it has a capability it cannot perform is the
 * documented way to get a plausible URL or a plausible price invented, which is
 * the one failure R13 exists to prevent and the one P19's eval suite tests for.
 *
 * ⚠️ **AND THE FALLBACK IS ALWAYS PRESENT RATHER THAN CONDITIONAL.**
 * {@see AgentSkill::MessageTaking} is `AlwaysOn`, so a business with nothing
 * configured still gets a briefing that says *take a message and hand off* — the
 * capture-and-handoff posture §2.4 describes, arrived at by every skill above it
 * being absent rather than by a special case anywhere.
 */
final readonly class AgentSkillSet
{
    /**
     * @param  list<AgentSkill>  $lit  In §2.2's own order — see the constructor.
     * @param  array<value-of<AgentSkill>, string>  $dark  Skill => the owner-facing
     *                                                     reason it is off, so the
     *                                                     wizard can say what is
     *                                                     missing without
     *                                                     re-deriving it.
     */
    private function __construct(
        public array $lit,
        public array $dark,
    ) {}

    /**
     * @param  list<AgentSkill>  $lit
     * @param  array<value-of<AgentSkill>, string>  $dark
     */
    public static function of(array $lit, array $dark): self
    {
        // ⚠️ SORTED BY THE SPEC'S ROW RATHER THAN BY RESOLUTION ORDER. The
        // briefing is a prompt, and a prompt whose lines reorder between two
        // otherwise identical requests is one that never reads a cached prefix —
        // the silent-invalidator shape, on the highest-volume AI path in the
        // product. Deriving it from the enum makes the order a fact about the
        // spec rather than about whichever resolver ran first.
        usort($lit, static fn (AgentSkill $a, AgentSkill $b): int => $a->specRow() <=> $b->specRow());

        return new self($lit, $dark);
    }

    public function has(AgentSkill $skill): bool
    {
        return in_array($skill, $this->lit, true);
    }

    /**
     * Whether this business has configured nothing — the capture-and-handoff
     * posture §2.4 describes.
     *
     * ⚠️ **DERIVED, NOT FLAGGED.** A boolean set by the resolver would be a
     * second opinion about the same facts; asking the set itself cannot disagree
     * with the set.
     *
     * ⛔ **THE SUBJECT IS "EVERY LIT SKILL NEEDS NO GROUNDING", NOT "EVERY LIT
     * SKILL IS IN §2.4's LIST", AND THE TWO GENUINELY DIFFER BY TWO ROWS (4138).**
     * This method was written the second way first and failed on a bare tenant.
     * The spec says two things that do not quite meet:
     *
     *  - **§2.4**: *"Skipped = the agent runs skills 1–3, 8–12, 15–16."*
     *  - **§2.2**: rows 13 (review ask) and 14 (nudge) are grounded on *"toggle,
     *    default ON"* and need nothing else.
     *
     * A business that skips the wizard leaves those two switches at their
     * default, which §2.2 states is ON — so under R13 they run, and §2.4's list
     * is two rows short of what actually happens. **§2.2's is the more specific
     * statement and the one encoded**: it names the default outright, where
     * §2.4's list is a summary of the posture.
     *
     * ⚠️ **P12 NARROWED THAT TO ONE ROW AND THIS PARAGRAPH IS CORRECTED RATHER
     * THAN LEFT STANDING.** Row 13 is no longer `AlwaysOn`: it is grounded on a
     * real, permitted, unspent feedback-page link
     * ({@see AgentGroundingSource::ReviewAskOffer}), so a bare tenant with a
     * feedback page and a contact who has never been invited **is** past this
     * predicate. That is the honest answer — such a business has a capability
     * that needs a fact about itself, which is what this method measures — and
     * §2.4's own list never contained row 13 anyway. Row 14 is unchanged and
     * still `AlwaysOn`.
     *
     * ⚠️ **THE CONFLICT IS NOT RESOLVED AWAY, IT IS RECORDED AND BOTH READINGS
     * ARE TESTED.** {@see AgentSkill::survivesASkippedWizard()} still returns
     * §2.4's literal list and has its own test, because that is a fact about the
     * document; this predicate answers the behavioural question. If the owner
     * rules that a skipped wizard should also silence the review ask, the change
     * is the toggles' default and not this method.
     */
    public function isCaptureAndHandoffOnly(): bool
    {
        foreach ($this->lit as $skill) {
            if ($skill->grounding() !== AgentGroundingSource::AlwaysOn) {
                return false;
            }
        }

        return true;
    }

    /**
     * The capability half of the system prompt — one line per lit skill.
     *
     * ⛔ **NOTHING ABOUT A DARK SKILL APPEARS HERE, EVER.** See the class
     * docblock. A test drives this red by asserting the briefing of a business
     * with no price list contains no mention of prices at all.
     *
     * ⚠️ **EVERY LINE IS OURS.** `AgentSkill::promptLine()` returns text written
     * in this repository and reviewed here, so nothing in this string needs a
     * fence. The untrusted values — the customer's message, the tenant's own
     * labels and disclaimer — enter the prompt through {@see AgentComposer},
     * wrapped, and never through this method.
     */
    public function capabilityBriefing(): string
    {
        $lines = [];

        foreach ($this->lit as $skill) {
            $lines[] = '- '.$skill->promptLine();
        }

        return implode("\n", $lines);
    }

    /**
     * What the wizard tells an owner is switched off, and why.
     *
     * @return array<value-of<AgentSkill>, string>
     */
    public function unavailable(): array
    {
        return $this->dark;
    }
}
