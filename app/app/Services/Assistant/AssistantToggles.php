<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Enums\AssistantToggle;
use App\Models\AssistantBrief;
use App\Services\Agent\AgentSkills;
use App\Support\Tenancy;

/**
 * The three switches a business has over its assistant — T176 §2.4, patch P4.
 *
 * ⛔ **THE ONLY READER AND WRITER OF THE THREE `*_enabled` COLUMNS ON
 * `assistant_briefs`, HELD THERE BY A LINT** (`tests/Feature/Architecture/PricesTest.php`)
 * — the same chokepoint rule as {@see PriceBook} and {@see UrgentTerms}, and for
 * the same reason those two carry it. The columns are **nullable**, and `null`
 * does not mean off:
 *
 *  - A business that has never opened the wizard has **no row at all**, so most
 *    tenants read `null` for all three.
 *  - `null` resolves to {@see AssistantToggle::defaultsOn()}, which is `true` for
 *    all three today.
 *
 * A second reader taking `$brief->quotes_enabled` gets `null` for the majority of
 * businesses, and `null` is falsy — so it would switch three default-ON features
 * off for almost everybody, on a screen that would look entirely correct. That is
 * the exact failure the disclaimer's own fallback exists to prevent, one column
 * group over.
 *
 * ## ⚠️ A TOGGLE IS NOT A PERMIT AND CANNOT BE READ AS ONE
 *
 * ⛔ **`isEnabled(ReviewAsk)` DOES NOT MEAN THIS PERSON MAY BE ASKED FOR A
 * REVIEW.** The consent record, the suppression registers, STOP, the invite
 * ledger's dedupe and the per-destination threshold are all upstream and
 * unchanged; this answers only whether the *business* wants their assistant to
 * offer it at all. Same for the nudge, which still rides quiet hours, and for
 * quotes, which still needs a reviewed price list. Every one of these is an
 * **and**, and a caller reading this as sufficient has skipped the gates that
 * carry the legal weight.
 *
 * ⚠️ **AND IT IS NOT THE GROUNDING EITHER.** {@see AgentSkills}
 * ands the two together; keeping them apart is what lets the owner's screen say
 * *"you turned this off"* rather than *"you have not written a price list"* when
 * those are different facts.
 */
final class AssistantToggles
{
    /**
     * Whether this business wants the skill behind `$toggle` to run at all.
     *
     * ⚠️ **NO ROW AND A NULL COLUMN ARE THE SAME ANSWER ON PURPOSE**, and both are
     * the platform default. Telling them apart would be a distinction with no
     * consequence — a business that opened the wizard and left a switch alone has
     * said exactly what a business that never opened it said, which is nothing.
     */
    public function isEnabled(AssistantToggle $toggle): bool
    {
        Tenancy::idOrFail();

        $stored = $this->brief()?->getAttribute($toggle->column());

        return is_bool($stored) ? $stored : $toggle->defaultsOn();
    }

    /**
     * Every switch and where it currently stands.
     *
     * For the wizard and the owner's settings screen, which show all three at
     * once — and a per-switch loop of {@see self::isEnabled()} would be three
     * reads of one row.
     *
     * @return array<value-of<AssistantToggle>, bool>
     */
    public function all(): array
    {
        Tenancy::idOrFail();

        $brief = $this->brief();
        $state = [];

        foreach (AssistantToggle::cases() as $toggle) {
            $stored = $brief?->getAttribute($toggle->column());

            $state[$toggle->value] = is_bool($stored) ? $stored : $toggle->defaultsOn();
        }

        return $state;
    }

    /**
     * Whether a person has actually chosen, rather than following the platform.
     *
     * The settings screen says which, because an owner looking at a switch that
     * is on has no other way to tell whether they turned it on — the same
     * question {@see PriceBook::disclaimerIsTheirOwn()} answers about the
     * disclaimer, and it matters here for the same reason: it is what tells them
     * whether a future change to the platform default will move under them.
     */
    public function isTheirOwnChoice(AssistantToggle $toggle): bool
    {
        Tenancy::idOrFail();

        return is_bool($this->brief()?->getAttribute($toggle->column()));
    }

    /**
     * Set a switch, or hand it back to the platform default.
     *
     * ⚠️ **`null` IS A REAL ARGUMENT AND MEANS *FOLLOW THE PLATFORM*, WHICH IS NOT
     * THE SAME AS `true` EVEN THOUGH THEY BEHAVE IDENTICALLY TODAY.** The two
     * diverge the moment a default moves, and a business that never chose should
     * move with it. `setDisclaimer(null)` makes the same distinction on the same
     * row.
     */
    public function set(AssistantToggle $toggle, ?bool $enabled): void
    {
        Tenancy::idOrFail();

        $this->briefForWriting()->forceFill([$toggle->column() => $enabled])->save();
    }

    private function brief(): ?AssistantBrief
    {
        return AssistantBrief::query()->first();
    }

    private function briefForWriting(): AssistantBrief
    {
        return $this->brief() ?? new AssistantBrief;
    }
}
