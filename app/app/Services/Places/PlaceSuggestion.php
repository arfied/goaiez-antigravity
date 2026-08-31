<?php

declare(strict_types=1);

namespace App\Services\Places;

/**
 * One row of the autocomplete dropdown: a place id and two lines of text.
 *
 * SEPARATE FROM PlaceCandidate ON PURPOSE, even though both carry a `place_id`
 * and a label. A candidate is the resolver's *answer* — something the ladder of
 * `24` §1.2.1 worked out from a link, carrying the rule that produced it and a
 * CID, and destined for a confirm card that persists. A suggestion is a thing
 * the visitor has not chosen yet. It has no rule, because Google guessed it from
 * four characters; it has no CID, because nothing looked one up; and it must
 * never reach ConfirmedPlace, because "the visitor was shown this" is not
 * "the visitor confirmed this".
 *
 * Collapsing the two would put an unconfirmed guess into the type that exists to
 * mean confirmed-enough-to-save, which is the exact failure `24` §1.2.3 is
 * written to prevent.
 *
 * TWO LINES RATHER THAN ONE. Google returns `structuredFormat.mainText` (the
 * business name) and `secondaryText` (the address) already separated, and a
 * dropdown needs them apart — the name is what the visitor scans for and the
 * address is what disambiguates the three Starbucks. Re-splitting a joined
 * string on the first comma, which is the alternative, breaks on every business
 * with a comma in its name.
 */
final readonly class PlaceSuggestion
{
    public function __construct(
        public string $placeId,
        public string $mainText,
        public ?string $secondaryText = null,
    ) {}

    /**
     * The whole suggestion as one line, for a screen reader or a narrow column.
     */
    public function label(): string
    {
        return $this->secondaryText === null
            ? $this->mainText
            : $this->mainText.', '.$this->secondaryText;
    }
}
