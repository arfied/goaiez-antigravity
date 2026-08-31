<?php

declare(strict_types=1);

namespace App\Services\Industries;

use App\Enums\IndustryFamily;
use App\Exceptions\WithheldRegistryValue;
use App\Services\Config\DefaultsRegistry;
use InvalidArgumentException;

/**
 * The demo door's two halves — CC-3 §3's *"one CTA: the demo door (keyword +
 * number from the demo registry, same rows CC-2's doors use)"*.
 *
 * ## The rows are CC-2's and this only reads them
 *
 * `demo.number` and the six `demo.keyword.{family}` rows belong to the marketing
 * slice that builds `/demo/{family}`. Reading them here rather than declaring a
 * second set is the whole point: the keyword printed on `/industries/plumbing`
 * and the keyword printed on `/demo/trades` are one fact, so they cannot disagree.
 *
 * ⛔ **AN UNSET NUMBER RENDERS NO CTA AT ALL, AND NEVER A PLACEHOLDER.** The
 * owner has not stated the demo number. A page that printed *"Text PLUMBING to
 * (555) 000-0000"* would be a live instruction to text a number that is not ours,
 * and *"Text PLUMBING to [number]"* is worse — it publishes an unfinished page
 * and looks deliberate. `DefaultsManifest`'s withheld mechanism already says the
 * conservative answer to an unset figure is to refuse to quote one; this is that
 * rule on a public page.
 *
 * ⚠️ **AND AN UNDECLARED KEY IS TREATED THE SAME AS A WITHHELD ONE, WHICH IS THE
 * ONE THING HERE THAT IS ABOUT THE BUILD RATHER THAN ABOUT THE PRODUCT.** These
 * pages and CC-2's demo rows are being built on two branches at once, so on this
 * one the manifest does not declare the keys yet and the registry answers an
 * undeclared key with `InvalidArgumentException`. To a public page the two
 * absences mean the identical thing — *we do not have a number to print* — and
 * the alternative was a 500 on `/industries` for every visitor between the two
 * merges. Decision 5228.
 */
final readonly class IndustryDemoDoors
{
    public const string NUMBER_KEY = 'demo.number';

    public function __construct(private DefaultsRegistry $defaults) {}

    /**
     * The number a visitor texts, or null while the owner has not stated one.
     */
    public function number(): ?string
    {
        $number = $this->read(self::NUMBER_KEY);

        return $number === '' ? null : $number;
    }

    /**
     * A family section's own keyword, or null while it is unset.
     */
    public function keywordFor(IndustryFamily $family): ?string
    {
        $keyword = $this->read($family->demoKeywordKey());

        return $keyword === '' ? null : $keyword;
    }

    private function read(string $key): ?string
    {
        try {
            $value = $this->defaults->value($key);
        } catch (WithheldRegistryValue|InvalidArgumentException) {
            return null;
        }

        return is_string($value) ? $value : null;
    }
}
