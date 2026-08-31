<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What one line of a diff is: text the draft adds, text it drops, or text the
 * two versions share.
 *
 * ⚠️ **THE MARKER AND THE WORD ARE THE SIGNAL; THE TINT IS WHAT MAKES THEM FAST
 * TO FIND.** `22` and `29` §2 forbid colour as the sole indicator, so the
 * pairing lives here rather than at the call site — `SignalState`'s reasoning in
 * its own words, applied to the one place in this application where a *row* has
 * a state rather than a business does.
 *
 * ⚠️ **IT IS DELIBERATELY NOT A `SignalState`.** Those three mean running,
 * attention and action — a vocabulary about whether somebody has to do
 * something. A removed line is not an alert and an added one is not "running";
 * borrowing the enum would spend that vocabulary on a reading aid, which is the
 * argument `x-ui.button`'s docblock already makes about a green Publish button.
 * What is borrowed is the *rule*: a token class, never a hex, so the `@theme`
 * block stays the only place a hue is defined and dark mode keeps working.
 */
enum DiffLineKind: string
{
    /** In the draft and not in the published text. */
    case Added = 'added';

    /** In the published text and not in the draft. */
    case Removed = 'removed';

    /** Identical in both. */
    case Unchanged = 'unchanged';

    /**
     * The half of the signal that survives a stylesheet failing to load.
     *
     * A minus sign rather than a hyphen: at the same size as `+` it reads as its
     * opposite, which a hyphen does not.
     */
    public function marker(): string
    {
        return match ($this) {
            self::Added => '+',
            self::Removed => '−',
            self::Unchanged => ' ',
        };
    }

    /**
     * The word a screen reader announces and a colour-blind reader reads.
     *
     * Past tense, because it names what the draft did to the published text
     * rather than what the reader is being asked to do about it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Added => 'Added',
            self::Removed => 'Removed',
            self::Unchanged => 'Unchanged',
        };
    }

    /**
     * The row tint. Never rendered without marker() and label() beside it.
     *
     * The text itself stays ink on every row: tinting the words as well would
     * put document text at the contrast of a signal colour, and this is text
     * somebody is reading closely rather than glancing at.
     */
    public function backgroundClass(): string
    {
        return match ($this) {
            self::Added => 'bg-ok-bg',
            self::Removed => 'bg-alert-bg',
            self::Unchanged => 'bg-paper',
        };
    }
}
