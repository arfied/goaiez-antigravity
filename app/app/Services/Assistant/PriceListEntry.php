<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Enums\PriceListItemSource;
use App\Services\Links\TenantLink;
use App\Support\Money;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * One thing a business charges for, as it leaves the store — T176 P5.
 *
 * ⚠️ **`$label` IS THE TENANT'S WORDS, WHICH MAKES IT UNTRUSTED**, and P6's
 * {@see TenantLink} says the same thing about its own: it is
 * typed by a business into a settings screen — or read out of a business's own
 * uploaded document — and then travels into a model prompt beside a member of
 * the public's message. Rail 1 fences untrusted fields; a price label is one,
 * and it is the one that looks trustworthy because it came from the customer's
 * own supplier. ⛔ **Nothing here edits it**, on `PromptFence`'s own reasoning:
 * a silent edit means the model judged something the person did not write, and
 * a per-request random marker does not need the text cleaned to hold.
 *
 * ## The two amounts, and why the shape is a method rather than a field
 *
 * R13 lets skill 4 give *"the tenant's price or range"*. A range is two figures;
 * a price is one. {@see isRange()} derives which from whether `$maxCents` is
 * set, and the store's own CHECK makes a degenerate range (`max = min`)
 * unstorable — so the derivation cannot disagree with the data the way a stored
 * `kind` column eventually would.
 */
final readonly class PriceListEntry
{
    /**
     * @param  string  $label  What the business calls the job. Untrusted (above).
     * @param  string  $slug  How skill 4 addresses this one among several.
     * @param  int  $minorUnits  The price, or the bottom of the range, in integer
     *                           minor units (`18` §Money handling). Zero is a
     *                           real answer — "we do that free" is a price.
     * @param  ?int  $maxCents  The top of the range, or null for a flat figure.
     * @param  string  $currency  ISO 4217, travelling with the figures because
     *                            `18` makes the currency part of the value.
     * @param  PriceListItemSource  $source  Provenance for the editor, never
     *                                       permission — see the enum.
     * @param  ?CarbonImmutable  $confirmedAt  When a person said this may be
     *                                         quoted. **`null` is not quotable**,
     *                                         and {@see PriceList} refuses to
     *                                         hold one.
     */
    public function __construct(
        public string $label,
        public string $slug,
        public int $minorUnits,
        public ?int $maxCents,
        public string $currency,
        public PriceListItemSource $source,
        public ?CarbonImmutable $confirmedAt,
    ) {
        if ($minorUnits < 0) {
            throw new InvalidArgumentException('A price cannot be negative.');
        }

        if ($maxCents !== null && $maxCents <= $minorUnits) {
            // The store's CHECK says the same thing, and this says it for a
            // hand-built DTO — a "range" whose ends are equal is a figure
            // written twice, and one that goes downwards is a figure pair
            // nobody could quote.
            throw new InvalidArgumentException(
                'The top of a price range has to be above the bottom. A single figure is a price, '
                .'not a range of no width.'
            );
        }
    }

    /**
     * Whether this is quotable at all.
     *
     * ⚠️ **THE ONE QUESTION SKILL 4 ASKS BEFORE IT SAYS A NUMBER**, and it is
     * about the review rather than about the figure. An unconfirmed entry has a
     * perfectly good price on it; what it does not have is a person who has
     * looked at it.
     */
    public function isConfirmed(): bool
    {
        return $this->confirmedAt !== null;
    }

    public function isRange(): bool
    {
        return $this->maxCents !== null;
    }

    /**
     * The price, or the bottom of the range.
     */
    public function amount(): Money
    {
        return Money::of($this->minorUnits, $this->currency);
    }

    /**
     * The top of the range, or null when this is a flat figure.
     */
    public function upperAmount(): ?Money
    {
        return $this->maxCents === null ? null : Money::of($this->maxCents, $this->currency);
    }
}
