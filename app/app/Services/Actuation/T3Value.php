<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\T3InjectionKind;

/**
 * The one place a value out of a change set becomes something a T3 operation may
 * carry, or nothing at all.
 *
 * ⚠️ **THIS IS THE THIRD BELT AND NOT THE MECHANISM.** What makes free-form HTML
 * unrepresentable is {@see T3InjectionKind} being closed and the
 * client module having no HTML sink; this refusal of `<` and `>` is a cheap
 * extra that costs nothing and makes the intent visible at every construction
 * site. **Do not let it read as the protection** (314–316): a sanitiser is
 * exactly the thing slice I's brief says is not enough, and if the two layers
 * above it were removed this one would not save anybody.
 *
 * ⚠️ **REFUSES RATHER THAN STRIPS.** A stripped value is a sentence with a word
 * missing, written onto a business's own website in their name; refusing means
 * that change set serves nothing and somebody can see why. `PixelCollector`'s
 * half (b) records the same choice for the same reason.
 */
final class T3Value
{
    /**
     * A trimmed, single-line, markup-free string of at most `$max` characters —
     * or null, which means the operation is not constructible.
     */
    public static function text(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || mb_strlen($trimmed) > $max) {
            return null;
        }

        // Control characters, which have no business in page text and are how a
        // value smuggles a line break into an attribute.
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $trimmed) === 1) {
            return null;
        }

        if (str_contains($trimmed, '<') || str_contains($trimmed, '>')) {
            return null;
        }

        return $trimmed;
    }

    /**
     * A same-site path: it begins with one `/`, carries no scheme and no host,
     * and cannot be talked into either.
     *
     * ⛔ **"INTERNAL" IS A PROPERTY OF THE TYPE HERE, NOT A CHECK AGAINST A
     * CONFIGURED DOMAIN.** A path is resolved by the browser against whatever
     * page the module is running on, so a value that cannot express a host
     * cannot leave the site — which is a stronger guarantee than comparing
     * against `locations.website_url`, a column whose real writer is slice B's
     * and which was `pixel_tenant_id`'s shape until it got one (§2.11.2).
     * `//evil.test` is the case this exists for: a valid protocol-relative URL,
     * one character away from a path.
     */
    public static function path(mixed $value, int $max = 512): ?string
    {
        $text = self::text($value, $max);

        if ($text === null) {
            return null;
        }

        if (! str_starts_with($text, '/') || str_starts_with($text, '//')) {
            return null;
        }

        // A backslash is a forward slash to several browsers' URL parsers, so
        // `/\evil.test` is protocol-relative in practice. Whitespace of any kind
        // is refused for the same family of reasons.
        if (preg_match('/[\s\\\\]/', $text) === 1) {
            return null;
        }

        return $text;
    }
}
