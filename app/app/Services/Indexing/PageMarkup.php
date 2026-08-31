<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Enums\IndexingRefusal;

/**
 * The two things Google's Indexing API is allowed to be used for, read off a
 * page's JSON-LD.
 *
 * ⛔ **THE RESTRICTION IS THE VENDOR'S AND IT IS QUOTED RATHER THAN
 * PARAPHRASED**: *"The Indexing API can only be used to crawl pages with either
 * JobPosting or BroadcastEvent embedded in a VideoObject"*
 * (`developers.google.com/search/apis/indexing-api/v3/using-api`, last updated
 * 2026-07-16 UTC, fetched 2026-08-20). `29` §11.2's row 9 turns it into a
 * build-failing gate — *"no non-JobPosting URL reaches the Indexing API"* — and
 * this class is the half of that gate which decides.
 *
 * ## Why the nesting is modelled rather than a flat list of types
 *
 * ⚠️ **"EMBEDDED IN" IS A LOAD-BEARING WORD AND A FLAT TYPE LIST CANNOT CARRY
 * IT.** The obvious implementation collects every `@type` on the page and asks
 * whether `VideoObject` and `BroadcastEvent` are both present — which passes a
 * page carrying two unrelated blocks, and passes in the **permissive**
 * direction, which is the direction that submits an ineligible URL to Google
 * under a spam policy. Google's own livestream example puts the event under the
 * video's `publication` property, and that is what this walks:
 *
 *     {"@type": "VideoObject", "publication": [{"@type": "BroadcastEvent", …}]}
 *
 * (`developers.google.com/search/docs/appearance/structured-data/video`, fetched
 * 2026-08-20.)
 *
 * ## Nothing in this application produces either kind of page
 *
 * ⛔ **AND THAT IS NOT A REASON TO SKIP THE GATE, IT IS A REASON TO WRITE IT NOW.**
 * `BUILD-PLAN` §2.11.1: *"no table holds a job opening anywhere in the schema or
 * `DATA-MODEL.md`"*, and `growth_pages.schema_json` is deliberately absent until
 * something writes it. So today every real page this platform publishes is
 * {@see self::none()} and every one of them is refused — which is the correct
 * answer, arrived at by the gate rather than by the absence of a caller.
 *
 * ⚠️ **{@see self::fromJsonLd()} HAS NO CALLER IN `app/`, AND IT IS SAID HERE
 * RATHER THAN LEFT TO BE FOUND** (272). `Publishing` passes {@see self::none()},
 * because that is what a growth page truly carries. The parser is the substance
 * of the gate and the only constructor that could ever produce an eligible page
 * — so it is driven directly, against nested and side-by-side markup, a full
 * schema.org IRI, a prefixed type and a type list. **Deleting it would leave the
 * gate assertable only against an object that is eligible by construction**,
 * which is a gate tested against its own answer.
 */
final readonly class PageMarkup
{
    private function __construct(
        public bool $jobPosting,
        public bool $liveBroadcast,
    ) {}

    /**
     * A page with none of the markup the Indexing API accepts.
     *
     * ⚠️ **THE HONEST DEFAULT FOR EVERY PAGE THIS PLATFORM PUBLISHES TODAY**, and
     * a named constructor rather than a bare `new self(false, false)` so that a
     * caller has to say which claim it is making.
     */
    public static function none(): self
    {
        return new self(false, false);
    }

    /**
     * Read the markup out of a page's decoded JSON-LD.
     *
     * Accepts what JSON-LD actually arrives as: a single node, a bare list of
     * nodes, or a `@graph` wrapper — and a `@type` that is either a string or a
     * list of strings, with or without a `https://schema.org/` prefix.
     */
    public static function fromJsonLd(mixed $decoded): self
    {
        return new self(
            self::containsType($decoded, 'JobPosting'),
            self::containsLiveBroadcast($decoded),
        );
    }

    /**
     * Whether Google permits this URL to be sent to the Indexing API at all.
     */
    public function eligibleForIndexingApi(): bool
    {
        return $this->jobPosting || $this->liveBroadcast;
    }

    /**
     * The refusal an ineligible page earns, or null when it is eligible.
     */
    public function refusal(): ?IndexingRefusal
    {
        return $this->eligibleForIndexingApi() ? null : IndexingRefusal::NotEligibleForIndexingApi;
    }

    /**
     * Any node anywhere in the document declaring the given schema.org type.
     */
    private static function containsType(mixed $node, string $type): bool
    {
        if (is_array($node)) {
            if (in_array($type, self::typesOf($node), true)) {
                return true;
            }

            foreach ($node as $child) {
                if (self::containsType($child, $type)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * A `VideoObject` whose own `publication` carries a `BroadcastEvent`.
     */
    private static function containsLiveBroadcast(mixed $node): bool
    {
        if (! is_array($node)) {
            return false;
        }

        if (in_array('VideoObject', self::typesOf($node), true)
            && array_key_exists('publication', $node)
            && self::containsType($node['publication'], 'BroadcastEvent')) {
            return true;
        }

        foreach ($node as $child) {
            if (self::containsLiveBroadcast($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The schema.org types this one node declares, normalised.
     *
     * ⚠️ **A `@type` MAY BE A LIST**, and it may be written as a full IRI —
     * `https://schema.org/JobPosting` — or with a `schema:` prefix. Matching the
     * bare string only would let a page state the restriction's own type in a
     * spelling the guard does not recognise, and refuse a page Google would have
     * accepted; the failure direction is safe, and being wrong in the safe
     * direction on a gate is still being wrong.
     *
     * @param  array<mixed>  $node
     * @return list<string>
     */
    private static function typesOf(array $node): array
    {
        $declared = $node['@type'] ?? null;

        if (is_string($declared)) {
            $declared = [$declared];
        }

        if (! is_array($declared)) {
            return [];
        }

        $types = [];

        foreach ($declared as $type) {
            if (! is_string($type)) {
                continue;
            }

            $bare = str_contains($type, '/')
                ? (string) substr($type, (int) strrpos($type, '/') + 1)
                : $type;

            $types[] = str_contains($bare, ':')
                ? (string) substr($bare, (int) strrpos($bare, ':') + 1)
                : $bare;
        }

        return $types;
    }
}
