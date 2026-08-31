<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\T3Operation;

/**
 * The complete list of things this platform may write into a page it does not
 * own, through the pixel — doc `41` Part 1's T3 row, verbatim: *"JSON-LD, meta,
 * FAQ blocks, internal links, alt text"*.
 *
 * ⛔ **THIS ENUM IS THE FIRST OF THE THREE STRUCTURAL LAYERS THAT MAKE FREE-FORM
 * HTML UNREPRESENTABLE, AND IT IS THE ONE THAT MATTERS MOST.** `BUILD-PLAN`
 * §2.11.3 slice I: *"never free-form HTML, because whatever this endpoint serves
 * gets written into a stranger's page, making it an injection surface into every
 * tenant site at once."* A closed backed enum means `from('html')` raises a
 * `ValueError` — there is no case to construct, so the payload builder cannot be
 * asked for one, and a change set whose `change_type` is anything but these five
 * contributes nothing. **That is a property of the type rather than of a
 * validator**, which is the distinction slice I's test list draws.
 *
 *   1. **This enum** — no markup kind exists to name.
 *   2. **The five operation classes** ({@see T3Operation}), each with typed,
 *      named fields and no free string that reaches a parser. Every one of them
 *      also refuses a value carrying `<`, which is a third belt rather than the
 *      mechanism.
 *   3. **The client module has no HTML sink at all** — no `innerHTML`, no
 *      `insertAdjacentHTML`, no `document.write`, no `Range`, held by a lint in
 *      `tests/Feature/Architecture/ActuationTest.php`. Every value it receives
 *      reaches the page through `textContent` or `setAttribute`, so a string
 *      full of markup lands on the page as visible text and never as elements.
 *
 * ⚠️ **THE VALUES ARE `site_changes.change_type`, AND THAT COLUMN IS A FREE
 * STRING** — the substrate deliberately does not constrain it (slice A), because
 * T1 writes kinds that mean nothing here. So this enum is what *reads* the
 * column into a typed vocabulary, and an unrecognised value is a change set this
 * tier cannot serve rather than an error: five tiers write into one table.
 */
enum T3InjectionKind: string
{
    /**
     * A `<script type="application/ld+json">` block of structured data.
     *
     * ⚠️ **NOT AN EXECUTABLE SCRIPT, AND THE TYPE ATTRIBUTE IS WHY.** A `script`
     * element whose `type` is not a JavaScript MIME type is a data block the
     * browser never runs; the module builds one with `createElement`, sets that
     * type, and assigns `JSON.stringify(...)` to `textContent`. It never sets
     * `src` and never hands anything to a markup parser.
     */
    case JsonLd = 'json_ld';

    /**
     * One `<meta>` upsert — the existing tag's content, or a new tag.
     */
    case Meta = 'meta';

    /**
     * Alt text for one image, matched on the `src` the page already carries.
     */
    case AltText = 'alt_text';

    /**
     * One internal link, whose destination is a **path on the same site** by
     * construction — see {@see T3InternalLink}.
     */
    case InternalLink = 'internal_link';

    /**
     * A question-and-answer block rendered into the module's own container.
     */
    case Faq = 'faq';
}
