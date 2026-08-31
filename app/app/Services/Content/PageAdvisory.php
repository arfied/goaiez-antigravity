<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\GrowthPageType;

/**
 * The T4 tier, in full: the same gated page, ready for the owner to paste.
 *
 * ⛔ **`handoff()` IS THIS AND `handoff()` IS NOT A STUB.** `CLAUDE.md`: *"Every
 * automation implements both `execute()` and `handoff()`"*, and **this path is
 * expected to work rather than apologise** — typically by producing something
 * the owner can act on by hand, which is this file's reading and not a
 * quotation. `41` Part 1 says the same thing from the other
 * end: T4 is *"a prioritised fix list the owner applies"*, a real tier rather
 * than the absence of one.
 *
 * ⚠️ **SO THE TEST ASSERTS CONTENT AND NEVER "DID NOT THROW"** (`BUILD-PLAN`
 * §2.11.4 D). What makes this hand-off usable is that every field the owner has
 * to type is here, already through the quality gate, with the address it belongs
 * at — and a test that only checked a non-null return would pass against an
 * empty one.
 *
 * ⚠️ **ONE IMPLEMENTATION SERVES BOTH THE CONTRACT AND THE WEAKEST RUNG**, which
 * is why there is no separate advisory builder: a T4 location and a location
 * whose adapter is unavailable want exactly the same thing, and two code paths
 * would be two chances for the second to be a worse version of the first.
 */
final readonly class PageAdvisory
{
    /**
     * @param  ?PageByline  $byline  The line rule 36 asks for, when the tenant
     *                               has given us one. ⚠️ **Nullable here and
     *                               nowhere on the site-writing path** (5745):
     *                               T4 publishes nothing — the owner does — so
     *                               the byline is offered rather than required,
     *                               and a tenant who has not named an About page
     *                               is still handed their copy.
     */
    public function __construct(
        public int $pageId,
        public GrowthPageType $type,
        public string $url,
        public string $title,
        public ?string $metaDescription,
        public string $content,
        public ?string $targetKeyword,
        public ?PageByline $byline = null,
    ) {}

    public static function for(PublishCandidate $candidate, string $websiteUrl, ?PageByline $byline = null): self
    {
        return new self(
            $candidate->id,
            $candidate->type,
            $candidate->urlOn($websiteUrl),
            $candidate->copy->title,
            $candidate->copy->metaDescription,
            $candidate->copy->content,
            $candidate->targetKeyword,
            $byline,
        );
    }

    /**
     * The whole thing as one block a person can copy.
     *
     * ⚠️ **PLAIN TEXT, LABELLED IN THE WORDS THE OWNER USES** (`22`'s outcome
     * rule). Not "meta_description" and not "H1": the labels name what the
     * person is about to paste into their own site's editor.
     *
     * ⚠️ **NO MARKUP AND NO HTML.** Whatever an owner pastes goes into their own
     * page; handing them tags they did not ask for is how a hand-off breaks a
     * template nobody here has seen.
     */
    public function asPasteableText(): string
    {
        $lines = [
            'Page address: '.$this->url,
            '',
            'Page title: '.$this->title,
        ];

        if ($this->metaDescription !== null && trim($this->metaDescription) !== '') {
            $lines[] = '';
            $lines[] = 'Search description: '.$this->metaDescription;
        }

        $lines[] = '';
        $lines[] = 'Page text:';
        $lines[] = $this->content;

        if ($this->byline !== null) {
            // ⚠️ **LAST, AND IN PLAIN WORDS.** It is what rule 36 asks a
            // published page to carry, and on this rung the person carrying it
            // is the one pasting — so it is offered as a line to include rather
            // than smuggled into the body they are copying.
            $lines[] = '';
            $lines[] = 'Add this line at the end: '.$this->byline->asPlainText();
        }

        return implode("\n", $lines);
    }
}
