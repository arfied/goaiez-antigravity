<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The six families the hundred industry pages group into — CC-3 §1, and the
 * same six doors CC-2 §2.7 builds `/demo/{family}` for.
 *
 * THE VALUES ARE THE DOOR NAMES, NOT OURS. Every case value is the segment
 * PIII-64A–E writes after `/demo/` on a row's Door line (`/demo/trades`,
 * `→ /demo/medspa`), so the authored corpus parses into this enum with no
 * translation step, and the registry key a hub section reads for its keyword is
 * derived from the same word rather than mapped to it. Changing one of these
 * values orphans a demo door.
 *
 * ⚠️ **THE HUB'S SIX SECTION HEADINGS ARE NOT THE SIX DOORS, AND {@see self::label()}
 * IS WHERE THAT IS RECONCILED.** PIII-72 §A1 names the hub's sections *"Home &
 * Trades · Auto · Personal Care & Pets · Food & Events · Local Professional ·
 * Fitness & Lessons"* — six labels, of which five correspond to a door and the
 * sixth does not: every fitness and lessons row in the corpus (93–100, 60) is
 * doored `/demo/care`, and `medspa` — which IS a door — appears in no heading at
 * all, because exactly one row of the hundred carries it (47, day-spa). CC-3 §3
 * requires each section to close with *its family demo door*, so the sections
 * follow the doors and `Fitness & Lessons` has no section of its own. Decision
 * 5223.
 *
 * ⚠️ **DECLARATION ORDER IS THE HUB'S SECTION ORDER**, and it is PIII-72 §A1's
 * order with the door that has no heading last. Nothing sorts these at render
 * time; `cases()` is the order.
 */
enum IndustryFamily: string
{
    case Trades = 'trades';
    case Auto = 'auto';
    case Care = 'care';
    case Food = 'food';
    case Office = 'office';
    case Medspa = 'medspa';

    /**
     * The hub's heading for this family's section.
     *
     * Outcome language (`22`): these name the kind of business a reader is
     * looking for themselves in, never the kind of demo we route them to.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trades => 'Home & Trades',
            self::Auto => 'Auto',
            self::Care => 'Personal Care & Pets',
            self::Food => 'Food & Events',
            self::Office => 'Local Professional',
            self::Medspa => 'Spa & Wellness',
        };
    }

    /**
     * The registry key holding this family's demo keyword.
     *
     * ⛔ **THE ROW IS NOT OURS TO CREATE.** CC-2 §2.7 owns `demo.number` and the
     * six `demo.keyword.{family}` rows, and this engine reads them so that the
     * keyword a hub section prints and the keyword the demo page prints are one
     * fact. Deriving the key from the case value rather than writing six
     * literals is what stops a seventh family arriving with a key nobody
     * remembered to add.
     */
    public function demoKeywordKey(): string
    {
        return 'demo.keyword.'.$this->value;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $family): string => $family->value, self::cases());
    }
}
