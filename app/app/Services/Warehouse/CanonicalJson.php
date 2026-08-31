<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use JsonException;
use RuntimeException;

/**
 * The one encoding used for anything whose bytes have to be reproducible.
 *
 * `29` §12.1 lists **replay fidelity** among the build-failing adversarial
 * tests, and `GOAIEZ_PIXEL_MASTER_BUILD` §5.1 states the property it is
 * asserting: L1 and L2 are *"derived and disposable — you must be able to
 * `TRUNCATE` and rebuild them from L0 without data loss"*, with §11 row 7
 * making the acceptance criterion **byte-identical results**.
 *
 * ⚠️ **"BYTE-IDENTICAL" IS A PROPERTY OF AN ENCODING, NOT OF A COMPARISON.**
 * Two rebuilds that produce equal *values* prove nothing about bytes; what makes
 * the claim provable is that there is exactly one way to write a given value
 * down. That is this class, and every rule below exists because breaking it
 * produces output that still looks right.
 *
 * The rules, and the failure each one prevents:
 *
 *  1. **Object keys are emitted in insertion order and callers must supply them
 *     in a fixed order.** PHP preserves insertion order for string keys, so
 *     `['b' => 1, 'a' => 2]` and `['a' => 2, 'b' => 1]` encode differently while
 *     comparing equal with `==`. Callers here build their arrays as literals in
 *     a written-down order for exactly that reason.
 *  2. **No float, ever.** `json_encode()` renders a float through
 *     `serialize_precision`, which is an **ini setting** — it is `-1` on this
 *     machine (shortest round-trip) and `17` on a default PHP build, and the two
 *     write `0.1 + 0.2` as different byte strings. A replay on a differently
 *     configured box would therefore differ while every value round-tripped
 *     correctly. Telemetry that is naturally fractional is carried as an integer
 *     in its smallest unit — the same rule CLAUDE.md already applies to money
 *     ("store integer cents + currency code everywhere") — or as a decimal
 *     string. Passing a float raises rather than guessing a precision.
 *  3. **`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, pinned.** These are
 *     defaults-off in PHP, so a caller that forgot them would write `\/` and
 *     `é` where this writes `/` and the raw UTF-8 bytes. Both are valid
 *     JSON and neither is the other's bytes.
 *  4. **`JSON_THROW_ON_ERROR` and `JSON_INVALID_UTF8_SUBSTITUTE` is NOT set.**
 *     Substitution would silently rewrite a malformed byte into U+FFFD, which is
 *     a *lossy* archive pretending to be a faithful one. Invalid UTF-8 raises.
 *  5. **A null-valued key is present and null; it is never omitted.** "Absent"
 *     and "null" are two different lines of JSON, and a derivation that drops
 *     nulls on one run and not another is the classic silent replay difference.
 *     `assertShape()` is what holds callers to it.
 *
 * ⚠️ **THIS CLASS DOES NOT MAKE JSON *SEMANTICALLY* CANONICAL** (no RFC 8785
 * key sorting, no number normalisation), and it must not be described as if it
 * did. It makes *our own* encoding deterministic, which is the whole of what the
 * replay gate needs. A third party's JSON is archived as an opaque string
 * (see [[L0Archive]]) rather than re-encoded through this, because re-encoding
 * somebody else's bytes is the one thing that cannot be made faithful.
 */
final class CanonicalJson
{
    /**
     * The flags. Written once so no caller can encode with a different set.
     */
    public const int FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    /**
     * Encode one value to its single permitted byte representation.
     *
     * @param  array<string, mixed>  $value
     *
     * @throws RuntimeException on a float, a resource, or invalid UTF-8
     */
    public static function encode(array $value): string
    {
        self::refuseFloats($value, '');

        try {
            return json_encode($value, self::FLAGS);
        } catch (JsonException $e) {
            throw new RuntimeException(
                'A value could not be canonically encoded: '.$e->getMessage().'. '
                .'The archive is byte-faithful or it is not an archive; nothing is substituted here.',
                previous: $e,
            );
        }
    }

    /**
     * Decode a canonical line back to an array.
     *
     * ⚠️ `JSON_BIGINT_AS_STRING` is deliberately absent and `depth` is left at
     * the default: a line this application wrote is a line this application can
     * read, and widening the decoder to accept shapes the encoder cannot produce
     * would hide a corrupted object rather than surface it.
     *
     * @return array<string, mixed>
     */
    public static function decode(string $line): array
    {
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('A canonical line decoded to '.get_debug_type($decoded).' rather than an object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Hold a caller to an exact key set, in an exact order.
     *
     * ⚠️ **THE ORDER IS THE POINT AND THE KEY SET IS THE SECOND POINT.** Rule 5
     * above says a null-valued key is written rather than dropped; this is what
     * enforces it, because the natural way to drop one is
     * `array_filter($row)` — which also drops `0`, `''` and `false`, and does it
     * in whichever rows happen to hold them. That produces an archive whose
     * lines have different shapes depending on their contents, which is
     * unreadable by any decoder written against the schema.
     *
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    public static function assertShape(array $row, array $keys, string $subject): void
    {
        $actual = array_keys($row);

        if ($actual !== $keys) {
            throw new RuntimeException(
                $subject.' does not match its canonical shape. Expected exactly ['
                .implode(', ', $keys).'] in that order; got ['.implode(', ', $actual).']. '
                .'A key that is absent on one run and null on another is a replay difference '
                .'that reads as correct in every value comparison.',
            );
        }
    }

    /**
     * @param  array<array-key, mixed>  $value
     */
    private static function refuseFloats(array $value, string $path): void
    {
        foreach ($value as $key => $item) {
            $here = $path === '' ? (string) $key : $path.'.'.$key;

            if (is_float($item)) {
                throw new RuntimeException(
                    'A float reached the canonical encoder at "'.$here.'". json_encode() renders floats '
                    .'through the serialize_precision ini setting, so the same value writes different bytes '
                    .'on differently configured machines and a replay stops being byte-identical. '
                    .'Carry it as an integer in its smallest unit, or as a decimal string.',
                );
            }

            if (is_array($item)) {
                self::refuseFloats($item, $here);
            }

            if (is_object($item) || is_resource($item)) {
                throw new RuntimeException(
                    'A '.get_debug_type($item).' reached the canonical encoder at "'.$here.'". '
                    .'Only scalars, null and arrays have one byte representation here.',
                );
            }
        }
    }
}
