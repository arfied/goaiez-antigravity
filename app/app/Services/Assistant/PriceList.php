<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use InvalidArgumentException;

/**
 * Everything a business will let its assistant quote — T176 P5, R13's skill 4.
 *
 * R13: *"quote **only** list items; give the tenant's price or range **+ the
 * tenant's disclaimer line**; off-list → never invent."*
 *
 * ⛔ **THE DISCLAIMER TRAVELS WITH THE PRICES RATHER THAN BESIDE THEM, AND THAT
 * IS THE WHOLE REASON THIS CLASS EXISTS.** A caller cannot obtain a figure to
 * quote without also holding the line R13 requires it to be quoted with: there
 * is no method that returns entries alone, and {@see disclaimer()} is never
 * empty. Handing back a bare list and putting the disclaimer on a second call
 * would make "quote the price" one line of code and "say the disclaimer" a
 * second one somebody forgets — and the one they forget is the one that turns an
 * estimate into a promise.
 *
 * ## ⛔ AN UNCONFIRMED ENTRY CANNOT BE PUT IN ONE
 *
 * The constructor refuses it, loudly. That is the second of the two layers over
 * the review-before-live gate — the first is `PriceBook`'s `whereNotNull` — and
 * they are independent on purpose: **a `where` is exactly the sort of thing a
 * later query forgets, and a constructor is not.** Dropping the filter turns the
 * suite red here rather than turning a proposal into a quote in production.
 *
 * ⛔ **NOTHING IN `app/` READS THIS YET.** Skill 4 is P4's, and P5 delivers the
 * store, the editor and this contract. The *writer* is real — the owner's own
 * editor — so the table is not `CLAUDE.md`'s 272 shape, but the reader is owed
 * and 4016 says so rather than leaving it to be discovered.
 */
final readonly class PriceList
{
    /**
     * @param  string  $disclaimer  The line said with every quote. Never empty:
     *                              {@see PriceBook::list()} falls back to the
     *                              platform's own wording for a business that has
     *                              not written one. ⚠️ **Untrusted when it is the
     *                              tenant's**, exactly as a label is.
     * @param  array<string, PriceListEntry>  $entries  Keyed by slug, so skill 4
     *                                                  can address one by the name
     *                                                  it was asked for.
     */
    private function __construct(
        private string $disclaimer,
        public array $entries,
    ) {}

    /**
     * @param  array<string, PriceListEntry>  $entries
     *
     * @throws InvalidArgumentException when the disclaimer is blank, or when any
     *                                  entry has not been confirmed by a person
     */
    public static function of(string $disclaimer, array $entries): self
    {
        if (trim($disclaimer) === '') {
            throw new InvalidArgumentException(
                'A price list has no disclaimer line. R13 requires one with every quote, and the '
                .'platform default is what a business that has written none is meant to get.'
            );
        }

        foreach ($entries as $entry) {
            if (! $entry->isConfirmed()) {
                throw new InvalidArgumentException(
                    "The price list was built with an unreviewed entry ({$entry->slug}). Nothing read "
                    .'off an uploaded document is quotable until a person confirms it — see '
                    .'price_list_items.confirmed_at.'
                );
            }
        }

        return new self($disclaimer, $entries);
    }

    /**
     * The one item skill 4 was asked about, or null.
     *
     * ⛔ **NULL IS "OFF-LIST", WHICH R13 ANSWERS WITH A CALLBACK RATHER THAN A
     * GUESS.** It is the ordinary answer, not a failure: a business prices the
     * dozen jobs it does most, and the thirteenth question is the one the owner
     * takes. Nothing here searches approximately, and nothing here falls back to
     * a nearby item — a price that is nearly right is the invention R13 forbids
     * wearing a plausible label.
     */
    public function quote(string $slug): ?PriceListEntry
    {
        return $this->entries[trim($slug)] ?? null;
    }

    /**
     * Whether skill 4 is grounded at all (R13).
     *
     * An empty list means the skill is **absent** — the assistant takes the
     * question and hands it to the owner — rather than free-handed.
     */
    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    /**
     * The line R13 requires with every quote.
     *
     * ⚠️ **A METHOD RATHER THAN A PUBLIC PROPERTY, WHICH IS THE OPPOSITE CALL TO
     * `$entries`** — and deliberately. Two ways to reach one value is two things
     * to keep in step; the entries are a plain collection with nothing to say
     * about themselves, and this is the half of the pair that carries a rule.
     */
    public function disclaimer(): string
    {
        return $this->disclaimer;
    }
}
