<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Support\SupportMacros;
use App\Support\LegalCanon;

/**
 * Every placeholder a support macro may carry — T308 §A2, CC-5 §0 and §4.
 *
 * T308's law: *"slots and [DATA] only, never typed numbers"*. This enum is that
 * law's vocabulary, and it exists rather than a loose regex because the guard
 * that stops a slot reaching a tenant has to know **which braces are ours**. A
 * guard refusing every `{…}` would refuse a staff reply that legitimately quoted
 * one — a lint tuned until it cries wolf is one that gets turned off (511) — and
 * a guard refusing none would be decoration.
 *
 * ## ⚠️ THE SYNTAX IS THE HOUSE'S; THE VOCABULARY IS THIS LIBRARY'S
 *
 * `ReactComposer` substitutes `{name}` and `{link}` in an outbound **text
 * message**. These are a support **reply**, composed by a person in a textarea,
 * so the words differ — but the shape does not: braces, lower case, snake_case.
 * That is CC-5 §0 honoured rather than forked, and it is why a macro slot and a
 * campaign slot look like the same kind of thing to whoever reads one.
 *
 * ## ⛔ TWO KINDS, AND THE DIFFERENCE IS WHO FILLS THEM
 *
 * Most are **agent-filled**: a status, a step, a link the agent pastes. One is
 * **canon-bound** — {@see self::GuaranteeSentence} — and is resolved from the
 * registry the moment the macro is inserted, never typed and never stored in the
 * macro body. That is CC-5 §2's rule applied to the support library: *"bind the
 * sentence via CC-4's one-source key, never paste it"*, so counsel changing the
 * promise changes every future paste.
 */
enum SupportMacroSlot: string
{
    /** S-1, S-7: where the thing they asked about actually stands. */
    case Status = 'status';

    /** S-1: what happens next, in one concrete step. */
    case Step = 'step';

    /** S-2: the price the list had. */
    case OldPrice = 'old_price';

    /** S-2: the price it should have had. */
    case NewPrice = 'new_price';

    /** S-4: the cancellation link, pasted first — T308's EXIT-FIRST rule. */
    case CancelLink = 'cancel_link';

    /** S-5: what we can do instead, per the rules. */
    case MakeGoodOptions = 'make_good_options';

    /** S-6: the privacy page, in plain words. */
    case PrivacyLink = 'privacy_link';

    /** S-8: what was logged, in the reporter's own terms. */
    case Repro = 'repro';

    /** S-9: the specific thing that should not have happened. */
    case WhatHappened = 'what_happened';

    /** S-9: the fix, in order. */
    case Steps = 'steps';

    /** S-10: the nearest existing path, today. */
    case NearestPath = 'nearest_path';

    /** S-0: the clock, stated — T308 §A4's *"one sentence, name attached"*. */
    case Time = 'time';

    /**
     * S-3: R39's guarantee, bound from the registry and never pasted.
     *
     * ⛔ **THE ONLY CANON-BOUND SLOT, AND THE ONLY ONE AN AGENT MAY NOT TYPE.**
     * T308 §A3 does not author S-3's body at all — it says *"the FM macro of
     * record, verbatim placement — the promise kept in one paste"* — because the
     * promise is R39's wording and lives in one place. {@see LegalCanon}
     * resolves it; {@see SupportMacros::rendered()} substitutes it.
     */
    case GuaranteeSentence = 'guarantee_sentence';

    /**
     * How the slot is written inside a macro body.
     */
    public function placeholder(): string
    {
        return '{'.$this->value.'}';
    }

    /**
     * The registry key this slot is bound to, or null when a person fills it.
     *
     * ⚠️ **`LegalCanon` OWNS THE KEY NAME AND THIS METHOD ONLY POINTS AT IT.**
     * A second spelling of `legal.guarantee_sentence` is a second key, and the
     * one that is never set is the one that fails closed at the worst moment.
     */
    public function canonKey(): ?string
    {
        return match ($this) {
            self::GuaranteeSentence => LegalCanon::GUARANTEE_SENTENCE_KEY,
            default => null,
        };
    }
}
