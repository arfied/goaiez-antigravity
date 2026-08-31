<?php

declare(strict_types=1);

namespace App\Services\Content;

/**
 * The words on a growth page, as one thing.
 *
 * ⚠️ **THREE FIELDS TRAVEL TOGETHER BECAUSE THE GATE JUDGES ALL THREE.** A
 * title is where a fabricated claim or an abusive phrase is most likely to
 * appear and most likely to be seen — it is the line a search result prints —
 * so a gate that read only the body would be a gate with a hole in the most
 * public part of the page.
 */
final readonly class PageCopy
{
    public function __construct(
        public string $title,
        public ?string $metaDescription,
        public string $content,
    ) {}

    /**
     * Everything a reader would see, in reading order.
     *
     * ⚠️ **THIS IS WHAT UNIQUENESS AND READABILITY ARE MEASURED OVER**, so two
     * pages whose bodies differ but whose titles and descriptions are identical
     * are correctly less unique than their bodies alone suggest. Doc `16`
     * §15.1's pattern 2 — *"only the city name changes"* — is usually visible in
     * the title first.
     */
    public function fullText(): string
    {
        return implode("\n\n", array_filter([
            $this->title,
            $this->metaDescription,
            $this->content,
        ], static fn (?string $part): bool => $part !== null && trim($part) !== ''));
    }
}
