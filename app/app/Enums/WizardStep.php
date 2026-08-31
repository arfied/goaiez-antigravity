<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The onboarding wizard's steps, in the order the owner walks them.
 *
 * ⚠️ THE VALUE IS THE IDENTITY, NOT THE POSITION. `wizard_progress.current_step`
 * shipped as an `integer`, which is a positional pointer — and `29` §7.2 lists
 * fourteen steps of which this slice can build five, with the unbuildable ones
 * sitting *between* the buildable ones (Connect Google is §7.2's step 3). An
 * integer is renumbered the moment a later row inserts its step, and every
 * wizard already in progress silently resumes on the wrong screen with nothing
 * anywhere raising an error. A string key survives insertion.
 *
 * ⛔ **ADDING A STEP LATER IS NOT CHEAP, AND THIS PARAGRAPH SAID IT WAS UNTIL
 * 2026-08-12.** It used to end *"add the case in its §7.2 position, add it to
 * ordered(), add its route, and add its label. **Nothing else reads position
 * numbers.**"* — and that last sentence is false. Two things read them:
 *
 *   - `database/migrations/2026_08_03_161908_change_wizard_progress_current_
 *     step_to_string.php`, whose `CASE` maps each integer to a step and is a
 *     **record of what those integers meant in rows written before it ran**;
 *   - `tests/Feature/Setup/WizardStepTest.php` — *"the down migration maps every
 *     step to its own position"* and *"the up migration maps every position back
 *     to its own step"*, both of which iterate `WizardStep::cases()` and assert
 *     the migration knows each one's `position()`.
 *
 * So inserting a case before `Done` shifts `Done` from 5 to 6, reddens both
 * assertions, and the only route back to green is **editing an applied
 * migration** — after which a rollback and re-migrate would move every finished
 * wizard to a step that did not exist when its row was written.
 *
 * ⚠️ **THE CORRECTION EXISTED AND WAS IN THE WRONG FILE.** Decision 2308 ruled
 * this sentence false and the write-up went into `Account\Knowledge`'s docblock
 * — a file somebody adding a wizard step has no reason to open, so the next
 * builder read this paragraph and was told the opposite (2915). Two slices have
 * now moved a screen out of the wizard rather than pay this cost:
 * `Account\Knowledge` (2308) and `Account\Calls` (2910).
 *
 * **Adding a step therefore means**: the case in its §7.2 position, `ordered()`,
 * its route, its label, **and a decision about the conversion migration and the
 * rows it already governs.** If the screen does not have to be part of the
 * one-time walk, `/account` is the cheaper and usually better home — a control
 * inside setup is one a tenant answers once and can never revisit, because
 * `SetupFlow` will not re-enter a completed step.
 */
enum WizardStep: string
{
    case Welcome = 'welcome';
    case FindBusiness = 'find_business';
    case HowCustomersReach = 'how_customers_reach';
    case ReviewRules = 'review_rules';
    case Done = 'done';

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return self::cases();
    }

    public static function first(): self
    {
        return self::Welcome;
    }

    /**
     * Outcome language (`22`): every label names what the owner is doing, never
     * what the system is doing.
     */
    public function label(): string
    {
        return match ($this) {
            self::Welcome => 'Welcome',
            self::FindBusiness => 'Find your business',
            self::HowCustomersReach => 'How customers reach you',
            self::ReviewRules => 'Review rules',
            self::Done => 'All set',
        };
    }

    public function routeName(): string
    {
        return 'setup.'.str_replace('_', '-', $this->value);
    }

    /**
     * 1-indexed, for display only. Never persisted — see the class docblock.
     *
     * The `?: 0` is for Larastan level 8: array_search() is typed int|false and
     * a false branch is unreachable here only because $this is always in
     * ordered(). Stating it costs one operator and keeps the build green
     * without an ignore.
     */
    public function position(): int
    {
        $index = array_search($this, self::ordered(), strict: true);

        return (is_int($index) ? $index : 0) + 1;
    }

    public function next(): ?self
    {
        return self::ordered()[$this->position()] ?? null;
    }

    public function previous(): ?self
    {
        return $this->position() < 2
            ? null
            : self::ordered()[$this->position() - 2];
    }
}
