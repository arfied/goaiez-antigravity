<?php

declare(strict_types=1);

namespace App\Services\Consent;

use InvalidArgumentException;

/**
 * A tenant's exported customer list, turned into contacts `CustomerImports` can
 * take.
 *
 * Deliberately dumb, and deliberately not a mapping UI. `CLAUDE.md`'s rule is
 * opinionated defaults over toggles, and a column-mapping step is a support
 * surface that earns its keep only once somebody has actually been unable to
 * import. A header row naming the columns is something every spreadsheet
 * produces, and the aliases below cover what the exports people actually have
 * call them.
 *
 * ⚠️ **IT NORMALISES NOTHING AND VALIDATES NOTHING ABOUT AN IDENTIFIER.** That
 * belongs to `App\Support\Identifier`, which `CustomerImports::resolve()`
 * already applies — a second normaliser here would be two places deciding what
 * a phone number is, and decisions 424–427 turn on a stored hash matching only
 * exactly. This splits a file into fields and stops.
 */
final class ContactFile
{
    /**
     * ⚠️ A CEILING RATHER THAN A PAGE SIZE. `import()` writes every contact and
     * its consent rows in one transaction, so a very large file is a long lock
     * and a request that may not finish. Refusing at a stated number is honest;
     * silently truncating would record an attestation whose `row_count` says the
     * tenant's list was smaller than it is.
     */
    public const MAX_ROWS = 5000;

    /**
     * Header names accepted for each field, lowercased and stripped of
     * punctuation.
     *
     * ⚠️ **`region` IS THE STATE AND NOTHING ELSE** (1594). It is the one field
     * `29` §2 rule 11 needs and the only part of an address this schema has a
     * column or a reader for, so the parser does not learn about street, city or
     * postcode — a spreadsheet's address columns are simply not read, which is
     * less stored personal data rather than a gap. ⚠️ **`st` is deliberately not
     * an alias**: it is as often "street" as "state", and a mis-mapped column
     * would put `Ma` into a jurisdiction field.
     */
    private const ALIASES = [
        'name' => ['name', 'full name', 'fullname', 'customer', 'customer name', 'contact', 'contact name', 'first name'],
        'email' => ['email', 'email address', 'e mail', 'emailaddress', 'mail'],
        'phone' => ['phone', 'phone number', 'mobile', 'mobile number', 'telephone', 'cell', 'cell phone', 'number'],
        'region' => ['state', 'state code', 'statecode', 'us state', 'state province', 'region', 'region code'],
    ];

    /**
     * @return list<array{name: ?string, email: ?string, phone: ?string, region: ?string}>
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string $csv): array
    {
        // A spreadsheet exporting UTF-8 writes a BOM, and an un-stripped BOM
        // makes the *first* header unmatchable while every other one works —
        // which reads as "your file has no email column" on a file that plainly
        // does.
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;

        $rows = self::rows($csv);

        if ($rows === []) {
            throw new InvalidArgumentException('That file is empty.');
        }

        $columns = self::columns(array_shift($rows));

        if ($columns['email'] === null && $columns['phone'] === null) {
            throw new InvalidArgumentException(
                'That file needs a column headed "email" or "phone" in its first row, so we know '
                .'which column is which. Nothing was imported.',
            );
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new InvalidArgumentException(
                'That file has '.number_format(count($rows)).' rows, and the most that can be '
                .'imported at once is '.number_format(self::MAX_ROWS).'. Split it and import each '
                .'part. Nothing was imported.',
            );
        }

        $contacts = [];

        foreach ($rows as $row) {
            $contact = [
                'name' => self::cell($row, $columns['name']),
                'email' => self::cell($row, $columns['email']),
                'phone' => self::cell($row, $columns['phone']),
                'region' => self::cell($row, $columns['region']),
            ];

            // A blank line at the end of a file is not a contact, and letting it
            // through would inflate `row_count` — the number the attestation
            // claims the tenant vouched for.
            //
            // ⚠️ `region` IS NOT PART OF THIS TEST, DELIBERATELY. A row with a
            // state and no name, email or phone is still nobody — counting it
            // would put a jurisdiction with no contact attached to it into the
            // attested `row_count`.
            if ($contact['name'] === null && $contact['email'] === null && $contact['phone'] === null) {
                continue;
            }

            $contacts[] = $contact;
        }

        return $contacts;
    }

    /**
     * @return list<list<string>>
     */
    private static function rows(string $csv): array
    {
        $rows = [];

        // Written through a memory stream rather than `explode("\n")` so a
        // quoted field containing a newline — an address, or a name with a line
        // break in it — stays one field. `str_getcsv` on a hand-split line
        // cannot do that, and the failure shows up as one mangled row somewhere
        // in the middle of an otherwise correct import.
        $handle = fopen('php://memory', 'r+');

        if ($handle === false) {
            throw new InvalidArgumentException('That file could not be read.');
        }

        fwrite($handle, $csv);
        rewind($handle);

        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row === [null]) {
                continue;
            }

            $rows[] = array_map(static fn (?string $cell): string => (string) $cell, $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @return array{name: ?int, email: ?int, phone: ?int, region: ?int}
     */
    private static function columns(array $header): array
    {
        $found = ['name' => null, 'email' => null, 'phone' => null, 'region' => null];

        foreach ($header as $index => $heading) {
            $normalised = self::normaliseHeading($heading);

            foreach (self::ALIASES as $field => $aliases) {
                // First match wins: a sheet with both "name" and "first name"
                // should use whichever came first rather than silently
                // preferring the later column.
                if ($found[$field] === null && in_array($normalised, $aliases, true)) {
                    $found[$field] = $index;
                }
            }
        }

        return $found;
    }

    private static function normaliseHeading(string $heading): string
    {
        $lowered = mb_strtolower(trim($heading));

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $lowered));
    }

    /**
     * @param  list<string>  $row
     */
    private static function cell(array $row, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }

        $value = trim($row[$index]);

        return $value === '' ? null : $value;
    }
}
