<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The version string to offer an admin starting a new legal document version.
 *
 * ⛔ **A SUGGESTION, AND NEVER A GENERATOR.** `LegalDocuments`' own docblock says
 * *"THE VERSION IS THE CALLER'S, NOT GENERATED"* — `39` seeds its drafts as
 * `0.9` and counsel's convention for a revision is counsel's to set. Nothing
 * here changes that: the service still takes whatever string it is handed, the
 * box on the screen stays editable, and this only stops the box being blank.
 * The rule that survives is the one that matters — a version, once published,
 * names exactly one text.
 *
 * ⚠️ **IT NEVER SUGGESTS A VERSION THAT IS ALREADY TAKEN**, because
 * `LegalDocuments::startDraft()` refuses one and the screen's standing rule is
 * that it never offers an action the service will refuse. A version history is
 * a handful of rows, so walking forward through it costs nothing.
 *
 * ⚠️ **AND IT WITHHOLDS RATHER THAN GUESSES.** A version this cannot read — a
 * date, a `v2`, `1.0-final` — produces an empty string and the box keeps its
 * placeholder. That is deliberate: the wrong suggestion on a version number is
 * worse than none, because the number is the pointer every stored consent record
 * resolves through (decision 330), and a plausible one is the one somebody
 * accepts without reading.
 */
final class NextLegalVersion
{
    /**
     * How far forward this will walk looking for a version nobody has used.
     *
     * A bound rather than a `while (true)`: the input is free-form text from a
     * table an admin writes, so "the next one is always free eventually" is an
     * assumption rather than a fact.
     */
    private const int ATTEMPTS = 50;

    /**
     * The version to pre-fill, given the published version and every version
     * that already exists.
     *
     * @param  list<string>  $taken  every version string of this document
     */
    public static function after(?string $current, array $taken = []): string
    {
        $candidate = $current === null || trim($current) === '' ? '1.0' : self::increment(trim($current));

        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            if ($candidate === '') {
                return '';
            }

            if (! in_array($candidate, array_map(trim(...), $taken), true)) {
                return $candidate;
            }

            $candidate = self::increment($candidate);
        }

        return '';
    }

    /**
     * One step on from a version, or nothing if the shape is not one this reads.
     *
     * ⚠️ **`0.9` GOES TO `1.0` AND `1.0` GOES TO `1.1`**, which is the owner's
     * own reading of these numbers rather than integer arithmetic on a second
     * component — every drafting pack in this repository seeds `0.9` and means
     * "nearly 1.0". So the minor part carries at ten, and a `major.minor` pair
     * is the only shape with that behaviour.
     */
    private static function increment(string $version): string
    {
        if (preg_match('/^(\d+)\.(\d+)$/', $version, $parts) === 1) {
            $major = (int) $parts[1];
            $minor = (int) $parts[2] + 1;

            // ⚠️ THE CARRY IS SCOPED TO A SINGLE-DIGIT MINOR, AND THAT IS NOT
            // FUSSINESS. `0.9` means "nearly 1.0" and carries; `1.10` means the
            // tenth revision of version 1 and its successor is `1.11`. Carrying
            // both would take a document from its tenth revision to a new major
            // version because of a digit count.
            return $minor === 10 && strlen($parts[2]) === 1
                ? ($major + 1).'.0'
                : $major.'.'.$minor;
        }

        if (preg_match('/^\d+$/', $version) === 1) {
            return (string) ((int) $version + 1);
        }

        return '';
    }
}
