<?php

declare(strict_types=1);

namespace App\Services\Content;

/**
 * Whose name is on a page this platform published, and where that name points.
 *
 * `29` §2 rule 36: *"auto-published content carries a real author byline linked
 * to a genuine About page."* The owner's ruling of 2026-08-20 (5720) makes the
 * default **the tenant's own company name**, linked to the About page **on their
 * own site** — a company genuinely is the publisher, so the byline is true
 * without anybody's name appearing on writing they did not do.
 *
 * ⛔ **THIS TYPE CANNOT REPRESENT A BYLINE WITH NOWHERE TO POINT** (5729). Both
 * fields are required and non-empty by construction, because the whole of what
 * survives the three overrules is *it links somewhere or the page does not
 * publish*. A nullable `aboutUrl` here would make the gate a field again.
 *
 * ⚠️ **NEITHER A PERSON NOR A PERSONA IS BUILT, AND BOTH ARE PERMITTED** (5721,
 * 5722). A real named person may carry the byline and defaults to the account
 * holder where one is used at all; an invented persona is permitted for content
 * by the owner's explicit overrule. **Neither is built here and neither should
 * be inferred from this class**: 5722's own carve-outs say a persona byline and
 * a fabricated biography are different acts, and a persona with nowhere to point
 * is not a byline. What ships is 5720's default and nothing beside it.
 */
final readonly class PageByline
{
    public function __construct(
        public string $name,
        public string $aboutUrl,
    ) {}

    /**
     * The line that goes on the page.
     *
     * ⚠️ **ESCAPED HERE RATHER THAN BY A TEMPLATE, BECAUSE NO TEMPLATE IS
     * INVOLVED.** This string is written into a stranger's website through
     * `CmsAdapter::writeChangeSet()`, not rendered by Blade — so a company name
     * carrying an apostrophe, an ampersand or a stray angle bracket is escaped
     * at the one place that knows it is about to become markup. {@see ChangeSet}
     * carries typed fields precisely so that no *other* value ever arrives here
     * as free-form HTML.
     *
     * ⚠️ **"Published by" RATHER THAN "Written by"** (`22`'s outcome rule, and
     * 5720's own reasoning). The company is the publisher; saying it wrote the
     * words would be a claim about authorship that 5722's carve-out is careful
     * not to authorise.
     */
    public function asHtml(): string
    {
        return '<p>Published by <a href="'.htmlspecialchars($this->aboutUrl, ENT_QUOTES, 'UTF-8').'">'
            .htmlspecialchars($this->name, ENT_QUOTES, 'UTF-8').'</a></p>';
    }

    /**
     * The same fact for somebody who is going to paste it by hand.
     *
     * ⚠️ **NO MARKUP**, on {@see PageAdvisory::asPasteableText()}'s rule: an
     * owner pasting tags they did not ask for into their own editor is how a
     * hand-off breaks a template nobody here has seen.
     */
    public function asPlainText(): string
    {
        return 'Published by '.$this->name.' — '.$this->aboutUrl;
    }
}
