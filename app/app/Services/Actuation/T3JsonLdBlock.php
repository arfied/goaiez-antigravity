<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;

/**
 * One block of structured data — `schema.org` JSON-LD.
 *
 * ⚠️ **A DATA GRAPH, NOT A STRING.** The obvious shape for this operation is a
 * ready-made JSON string the module drops into a script element, and that is the
 * shape slice I's brief forbids: *"a string field that is merely sanitised is not
 * this."* What travels instead is a decoded map whose every leaf has been proved
 * scalar and markup-free, and the module's own `JSON.stringify` is what turns it
 * back into text — so **the bytes on the page are produced by a serialiser on
 * the client and were never markup on either side of the wire**.
 *
 * ⛔ **`29` §2: "Accurate schema only — never a filtered or 5-star-only
 * aggregate."** This class cannot tell an honest `AggregateRating` from a
 * dishonest one and does not try; what it enforces is the shape. The claim rule
 * belongs to whatever generates the change set — slice D — and is named here so
 * that it is not mistaken for being enforced.
 *
 * ⚠️ **DEPTH AND SIZE ARE BOUNDED** because this is fetched on every page view of
 * somebody else's website, and because an unbounded nested structure is a way to
 * spend a visitor's CPU in `JSON.stringify` rather than a way to describe a
 * business.
 */
final readonly class T3JsonLdBlock implements T3Operation
{
    public const int MAX_DEPTH = 6;

    public const int MAX_NODES = 200;

    public const int MAX_LEAF = 1024;

    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(public array $data) {}

    /**
     * @param  array<string, mixed>  $fields  A change set's `after` map.
     */
    public static function fromFields(array $fields): ?self
    {
        $raw = $fields['json_ld'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return null;
        }

        // ⚠️ A BLOCK WITH NO `@type` IS NOT STRUCTURED DATA. Google ignores it,
        // so publishing one would be a change with no possible effect that the
        // measurement slice would then dutifully report a verdict on.
        if (! isset($raw['@type']) || ! is_string($raw['@type'])) {
            return null;
        }

        $nodes = 0;

        $clean = self::clean($raw, 1, $nodes);

        if (! is_array($clean) || $clean === []) {
            return null;
        }

        /** @var array<string, mixed> $clean */
        return new self($clean);
    }

    public function kind(): T3InjectionKind
    {
        return T3InjectionKind::JsonLd;
    }

    public function toPayload(): array
    {
        return ['t' => $this->kind()->value, 'd' => $this->data];
    }

    /**
     * The value with every leaf proved, or null if any part of it is not
     * expressible.
     *
     * ⚠️ **NULL PROPAGATES ALL THE WAY OUT** — one unusable leaf refuses the
     * whole block, on {@see T3FaqBlock}'s reasoning: structured data that
     * silently lost a field describes the business inaccurately, which is the
     * one thing schema markup must not do.
     */
    private static function clean(mixed $value, int $depth, int &$nodes): mixed
    {
        if (++$nodes > self::MAX_NODES || $depth > self::MAX_DEPTH) {
            return null;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return T3Value::text($value, self::MAX_LEAF);
        }

        if (! is_array($value)) {
            return null;
        }

        $out = [];

        foreach ($value as $key => $item) {
            // ⚠️ AN ARRAY KEY IS AN `int` OR A `string` AND THERE IS NO THIRD
            // CASE, so the list arm needs no check of its own — a guard for one
            // would be a branch that cannot fire, which is 256's shape at the
            // scale of an `if`.
            if (is_string($key)) {
                // `@context`, `@type`, `name` — a key is a property name and
                // nothing else. Refusing markup in it costs nothing and closes
                // the one place a key could carry a value.
                $key = T3Value::text($key, 64);

                if ($key === null) {
                    return null;
                }
            }

            $cleaned = self::clean($item, $depth + 1, $nodes);

            if ($cleaned === null) {
                return null;
            }

            $out[$key] = $cleaned;
        }

        return $out;
    }
}
