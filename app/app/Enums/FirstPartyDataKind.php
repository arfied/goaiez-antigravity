<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kinds of genuine first-party data a growth page may be anchored in — doc
 * `16` §15.3, verbatim: *"real review quote, real service detail, real photo,
 * real pricing, real local fact"*.
 *
 * ⚠️ **FIVE BECAUSE THE DOCUMENT NAMES FIVE.** `16` §15.2 is explicit that this
 * is the whole structural argument for generating pages at all — *"we have
 * unique first-party data no generic AI writer has"* — so the list is the
 * document's and widening it is a content decision rather than a coding one.
 *
 * ⛔ **THE KIND IS NOT THE CHECK.** The gate does not count declarations; it
 * counts declarations whose value it can find in the page copy. A candidate that
 * cites a price and then never mentions it is a page with a claim about itself,
 * which is what `16` §15.1's pattern 2 looks like from the inside.
 */
enum FirstPartyDataKind: string
{
    /** Words a real customer wrote. */
    case ReviewQuote = 'review_quote';

    /** Something specific this business actually does. */
    case ServiceDetail = 'service_detail';

    /** A real photograph, cited by whatever identifies it on the page. */
    case Photo = 'photo';

    /** A real price. */
    case Pricing = 'pricing';

    /** Something true about this place that a stranger would not know. */
    case LocalFact = 'local_fact';
}
