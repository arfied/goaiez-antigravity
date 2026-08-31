<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The three switches §2.4 gives a business over its assistant — T176 P4.
 *
 * *"toggles: quotes on/off · review-ask on/off · nudge on/off."* Three, named,
 * and no more.
 *
 * ⛔ **THIS IS THE SECOND DELIBERATE EXCEPTION TO "NEVER ADD A TENANT-FACING
 * TOGGLE", AND IT IS THE SPEC'S RATHER THAN A PREFERENCE.** CLAUDE.md's operating
 * instruction is absolute and records that the owner has overruled it exactly
 * once, for the invite threshold — *"do not read the exception as the rule; the
 * next toggle needs its own"*. T176 §2.4 is that ruling for these three, and the
 * enum is closed so that a fourth needs one too: adding a case is a visible act
 * with a test attached, where a fourth boolean column is a two-minute migration
 * nobody reviews.
 *
 * ⚠️ **AND ALL THREE DEFAULT ON, WHICH IS THE OPPOSITE OF THE SAFE-LOOKING
 * CHOICE.** §2.2 says so for the review ask and the nudge in as many words
 * (*"toggle, default ON"*), and the same follows for quotes: a switch that ships
 * off is a feature nobody discovers, and `automation_mode = 'auto'` is this
 * platform's standing answer to that question. The switch exists so a business
 * can stop something, never so they have to start it.
 *
 * ⛔ **A TOGGLE IS NOT A GROUNDING AND THE TWO ARE AND-ED.** Skill 4 needs a
 * price list *and* the quotes switch; skills 13 and 14 need only their switch.
 * Collapsing them would make "I turned quotes off" and "I have not written a
 * price list" the same state on the owner's screen, and they have different
 * remedies.
 */
enum AssistantToggle: string
{
    /**
     * Whether the assistant may quote from the price list at all (skill 4).
     *
     * ⚠️ **A BUSINESS WITH A PRICE LIST MAY STILL NOT WANT IT SAID OVER TEXT**,
     * which is the whole reason this switch exists beside the grounding. Off, the
     * assistant takes the question and hands it to the owner — the same behaviour
     * as a business that priced nothing, arrived at by a different route.
     */
    case Quotes = 'quotes';

    /**
     * Whether the assistant may offer the review link on a resolved thread
     * (skill 13, the bridge to SL-1).
     */
    case ReviewAsk = 'review_ask';

    /**
     * Whether the assistant may send one nudge after an unanswered link
     * (skill 14).
     */
    case Nudge = 'nudge';

    /**
     * The `assistant_briefs` column that holds it.
     *
     * ⚠️ **NULLABLE IN THE SCHEMA AND NOT NULLABLE HERE.** A business that has
     * never opened the wizard has no row at all, so the reader resolves `null` to
     * {@see self::defaultsOn()} — see `AssistantToggles`. Storing a default at
     * provisioning instead would freeze today's answer into every tenant, which
     * is `reviews.default_invite_threshold`'s lesson (1420) in a boolean.
     */
    public function column(): string
    {
        return match ($this) {
            self::Quotes => 'quotes_enabled',
            self::ReviewAsk => 'review_ask_enabled',
            self::Nudge => 'nudge_enabled',
        };
    }

    /**
     * What this switch means with nobody having touched it.
     *
     * All three are `true`; the method exists rather than a constant because the
     * next toggle may not be, and a caller reading a per-case answer cannot be
     * surprised by that the way one reading a shared constant would be.
     */
    public function defaultsOn(): bool
    {
        return match ($this) {
            self::Quotes, self::ReviewAsk, self::Nudge => true,
        };
    }

    /**
     * What the switch is called on the owner's screen.
     */
    public function ownerLabel(): string
    {
        return match ($this) {
            self::Quotes => 'Quote prices over text',
            self::ReviewAsk => 'Ask happy customers for a review',
            self::Nudge => 'Follow up once if nobody replies',
        };
    }
}
