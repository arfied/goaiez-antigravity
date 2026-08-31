<?php

declare(strict_types=1);

namespace Tests\Support;

use Symfony\Component\Finder\SplFileInfo;

/**
 * A Blade template that exists only inside the test that wrote it.
 *
 * ⛔ **THIS EXISTS SO A LINT CAN BE SHOWN A KNOWN VIOLATION, WHICH IS THE ONE
 * THING A FLOOR CANNOT DO** (2026-08-28, 11583). `ScreenStates`' five lints
 * each read a population and then classify each member as *already covered* or
 * *a violation*. `ScreenStatesTest`'s floor watches the population. **Nothing
 * watched the classifier**, and a classifier that answers *"already covered"*
 * to everything empties the offender list without moving the population by one
 * — measured three times, on three different classifiers, each with a real
 * violation planted in a real template and the file GREEN at the baseline's own
 * 6 tests and 18 assertions. The corpus cannot supply the negative case,
 * because the corpus is green: every record list in it HAS an empty state.
 * **So the negative case has to be written down, and this is what it is written
 * on.**
 *
 * ⚠️ **NOTHING IS WRITTEN TO DISK, DELIBERATELY.** A committed test that writes
 * a fixture into an untracked directory is green on one checkout on earth. The
 * path handed to the parent constructor is never opened: `getContents()` is
 * overridden and every other read `ScreenStates` performs goes through
 * `getRelativePathname()`, which `Symfony\Component\Finder\SplFileInfo` stores
 * rather than derives.
 *
 * ⛔ **IT IS NOT A MOCK OF THE LINT'S INPUT, IT IS THE LINT'S INPUT.** The
 * control calls the same public `ScreenStates` method the lint calls, on an
 * object of the same class the corpus scan produces, so a classifier that
 * fails open fails the control by the same code path. A control that
 * reimplemented the matcher would be 8460's twin and would agree with the
 * defect.
 */
final class InMemoryBladeFile extends SplFileInfo
{
    public function __construct(private readonly string $blade, string $relativePathname)
    {
        parent::__construct(
            base_path('resources/views/livewire/'.$relativePathname),
            (string) preg_replace('#/?[^/]+$#', '', $relativePathname),
            $relativePathname,
        );
    }

    public function getContents(): string
    {
        return $this->blade;
    }
}
