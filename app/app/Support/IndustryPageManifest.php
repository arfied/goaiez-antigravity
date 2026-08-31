<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\IndustryFamily;
use App\Exceptions\IndustryCorpusInvalid;

/**
 * THE INDUSTRY PAGE MANIFEST — CC-3 §2, and PIII-64A–E's own header law:
 * *"an industry page RENDERS from the profile registry + the row below — edits
 * happen in rows, never in HTML."*
 *
 * `LegalDraftManifest`'s sibling, one directory over and one degree further: the
 * legal manifest declares thirteen files whose whole body is the value, and this
 * one declares five files whose *grammar* is the value. Same shape otherwise —
 * a reviewed declaration of what a fresh install starts with, read by one
 * idempotent command, with the files under `database/seeders/` because they are
 * seed data and nothing renders them as an asset.
 *
 * ## The authored files are the data, and they are COPIED rather than read in place
 *
 * `LegalDraftManifest`'s argument, unchanged and for the same reason: reading
 * `docs/incoming-2026-08-18/…` at seed time would make a documentation directory
 * load-bearing for a production install, and this project has already archived
 * four documents out from under their own citations (decisions 135–138). The
 * copies under {@see self::directory()} are what ships; the delivery drop stays
 * where it landed.
 *
 * ## THE PARSE CONTRACT — two row shapes, one grammar
 *
 * CC-3 §2 names both, and they differ only in where the header line ends:
 *
 *   Shape A (files A/B)  `### NN · /industries/{slug}`
 *                        then `H1: "…" · Title: "…" · Desc: "…" · …`
 *   Shape B (files C/D/E) `### NN · /industries/{slug} — H1: "…" · Title: "…" · …`
 *
 * ⚠️ **NEITHER SHAPE PUTS ONE FIELD PER LINE, WHICH IS THE TRAP IN CC-3's OWN
 * WORDING.** The brief calls them *"labeled lines"*; in the authored files they
 * are labelled *runs* inside a hard-wrapped paragraph, so `Trio:` and the field
 * after it routinely share a line and a single field routinely spans three. This
 * parser therefore collapses each row's whitespace to single spaces first and
 * splits on the **label set** — never on the `·` separator, which is also what
 * `Trio:` splits its own three values on. Splitting the paragraph on `·` reads
 * as the obvious implementation and silently shreds every trio.
 *
 * ## Family is the file's routing, overridden by the row
 *
 * Each authored file's header states its door ranges (*"Rows 41–46 → /demo/auto ·
 * 47–52 → /demo/care (spa → /demo/medspa)"*), and a row may carry its own
 * `→ /demo/X`. {@see self::FAMILY_RANGES} is those headers transcribed, and an
 * explicit door on the row wins. **File A declares no ranges and needs none** —
 * all twenty of its rows carry an explicit door — and a row that resolves to
 * neither halts rather than defaulting, because a page filed under a family the
 * author did not choose is a wrong cross-link rather than a missing one.
 *
 * ## Validation runs on parse, not on seed, and never skips
 *
 * CC-3 §2 asks for the checks at seed time; they live here instead, because
 * {@see self::rows()} is what both the seeder and the architecture lint call, and
 * a check the lint cannot reach is a check that only fires on a fresh install.
 * Nothing here returns a partial set: every fault raises
 * {@see IndustryCorpusInvalid} naming the file and the row.
 */
final class IndustryPageManifest
{
    /**
     * PIII-64A's header: *"H1 ≤44 chars"*, and the meta pair from `docs/33`
     * Part 3's on-page checklist (*"title (≤60 chars)"*, a description in the
     * 150–160 band) tightened to CC-3 §2's own 155.
     *
     * ⚠️ **THESE ARE ALSO THE COLUMN WIDTHS.** `industry_pages` declares
     * `string('h1', 44)` and its siblings, so a corpus that slipped past this
     * check would be refused by Postgres rather than truncated. The pair is
     * deliberate: the constant names the row that is too long, the column makes
     * it impossible to store one.
     */
    public const int H1_MAX = 44;

    public const int TITLE_MAX = 60;

    public const int META_DESC_MAX = 155;

    /**
     * PIII-64E's own title: *"THE HUNDRED COMPLETES"*.
     */
    public const int EXPECTED_ROWS = 100;

    /**
     * The five authored files, in `position` order.
     *
     * ⛔ **DECLARED RATHER THAN GLOBBED**, on `LegalDraftManifest::documents()`'s
     * reasoning: a glob over the directory answers "whatever is there" and a
     * missing page-turn would present as a corpus of eighty that the row count
     * catches only because the count happens to be checked. Named, a missing
     * file is named.
     *
     * @var list<string>
     */
    private const FILES = [
        'PIII-64A-INDUSTRY-PAGES-01-20-T291.md',
        'PIII-64B-INDUSTRY-PAGES-21-40-T292.md',
        'PIII-64C-INDUSTRY-PAGES-41-60-T293.md',
        'PIII-64D-INDUSTRY-PAGES-61-80-T294.md',
        'PIII-64E-INDUSTRY-PAGES-81-100-T295.md',
    ];

    /**
     * The door ranges each authored file's header states, transcribed.
     *
     * PIII-64B: *"all doors → /demo/trades"* · PIII-64C: *"Rows 41–46 →
     * /demo/auto · 47–52 → /demo/care (spa → /demo/medspa) · 53–57 → /demo/food ·
     * 58–59 → /demo/office · 60 → /demo/care"* · PIII-64D: *"Rows 61–72 →
     * /demo/trades · 73–74 → /demo/office · 75–78 → /demo/auto · 79–80 →
     * /demo/care"* · PIII-64E: *"Rows 81–85 + 87–92 → /demo/office · 86 →
     * /demo/trades · 93–100 → /demo/care"*.
     *
     * PIII-64A states no ranges, so rows 1–20 are absent here on purpose.
     *
     * @var list<array{int, int, string}>
     */
    private const FAMILY_RANGES = [
        [21, 40, 'trades'],
        [41, 46, 'auto'],
        [47, 52, 'care'],
        [53, 57, 'food'],
        [58, 59, 'office'],
        [60, 60, 'care'],
        [61, 72, 'trades'],
        [73, 74, 'office'],
        [75, 78, 'auto'],
        [79, 80, 'care'],
        [81, 85, 'office'],
        [86, 86, 'trades'],
        [87, 92, 'office'],
        [93, 100, 'care'],
    ];

    /**
     * The nine slots of the row grammar, in the order the corpus writes them.
     *
     * PIII-64A's key: *"slug · H1(≤44) · meta title(≤60) / desc(≤155) · pain hook
     * (their words) · scenario beat · the trio · trust beat · demo door · FAQ
     * picks."*
     *
     * @var list<string>
     */
    private const LABELS = ['H1', 'Title', 'Desc', 'Hook', 'Beat', 'Trio', 'Trust', 'Door', 'FAQ'];

    /**
     * Where the installed corpus lives.
     */
    public static function directory(): string
    {
        return database_path('seeders/industry-pages/'.'source');
    }

    /**
     * The hundred rows, parsed and validated.
     *
     * @return list<array{
     *     position: int,
     *     slug: string,
     *     family: IndustryFamily,
     *     h1: string,
     *     title: string,
     *     meta_desc: string,
     *     hook: string,
     *     beat: string,
     *     trio: list<string>,
     *     trust: string,
     *     demo_keyword: string,
     *     faq_picks: list<int>,
     * }>
     *
     * @throws IndustryCorpusInvalid
     */
    public static function rows(): array
    {
        $rows = [];

        foreach (self::FILES as $file) {
            foreach (self::parseFile($file) as $row) {
                $rows[] = $row;
            }
        }

        self::assertCorpusIsWhole($rows);

        return $rows;
    }

    /**
     * @return list<array{
     *     position: int, slug: string, family: IndustryFamily, h1: string, title: string,
     *     meta_desc: string, hook: string, beat: string, trio: list<string>, trust: string,
     *     demo_keyword: string, faq_picks: list<int>,
     * }>
     *
     * @throws IndustryCorpusInvalid
     */
    private static function parseFile(string $file): array
    {
        $path = self::directory().'/'.$file;

        $contents = is_file($path) ? (string) file_get_contents($path) : '';

        if (trim($contents) === '') {
            throw IndustryCorpusInvalid::forFile(
                $file,
                "expected the authored page-turn at {$path}. Copy it from the delivery drop "
                .'(docs/incoming-2026-08-18/pack/09-code-patch/) — the authored files ARE the data.'
            );
        }

        // The `### NN · /industries/…` heading opens every row. The first
        // fragment is the file's own preamble and the last carries the fix log
        // and self-check trailing the final row, which `sectionOf()` cuts.
        $blocks = preg_split('/^### /m', $contents) ?: [];
        array_shift($blocks);

        if ($blocks === []) {
            throw IndustryCorpusInvalid::forFile($file, 'no `### NN · /industries/{slug}` rows found at all.');
        }

        return array_map(
            static fn (string $block): array => self::parseRow($file, $block),
            $blocks,
        );
    }

    /**
     * @return array{
     *     position: int, slug: string, family: IndustryFamily, h1: string, title: string,
     *     meta_desc: string, hook: string, beat: string, trio: list<string>, trust: string,
     *     demo_keyword: string, faq_picks: list<int>,
     * }
     *
     * @throws IndustryCorpusInvalid
     */
    private static function parseRow(string $file, string $block): array
    {
        // Everything up to the next `## ` heading — the fix log and the
        // self-check ride on the tail of the last row's block otherwise.
        $body = (string) (preg_split('/^## /m', $block)[0] ?? '');

        // ⚠️ ONE LINE, AND THIS IS WHAT MAKES BOTH SHAPES ONE PARSE. A hard-wrap
        // is not a field boundary here; see the class docblock.
        $one = trim((string) preg_replace('/\s+/u', ' ', $body));

        if (preg_match('~^(\d{1,3}) · /industries/([a-z0-9-]+)\s*(?:—\s*)?(.*)$~u', $one, $header) !== 1) {
            throw IndustryCorpusInvalid::forFile(
                $file,
                'a row heading does not match `NN · /industries/{slug}`: "'.mb_substr($one, 0, 80).'".'
            );
        }

        $position = (int) $header[1];
        $slug = $header[2];
        $fields = self::fieldsOf($file, $position, $slug, $header[3]);

        $h1 = self::clean($fields['H1']);
        $title = self::clean($fields['Title']);
        $metaDesc = self::clean($fields['Desc']);
        $trio = self::trioOf($file, $position, $slug, $fields['Trio']);

        // ⚠️ THE DOOR IS READ RAW, BEFORE `clean()`. The keyword is delimited by
        // the markdown emphasis `clean()` strips, so a cleaned door line loses
        // the only thing that says which of its words is the keyword.
        $demoKeyword = self::demoKeywordOf($file, $position, $slug, $fields['Door']);
        $family = self::familyOf($file, $position, $slug, $fields['Door']);

        $row = [
            'position' => $position,
            'slug' => $slug,
            'family' => $family,
            'h1' => $h1,
            'title' => $title,
            'meta_desc' => $metaDesc,
            'hook' => self::clean($fields['Hook']),
            'beat' => self::clean($fields['Beat']),
            'trio' => $trio,
            'trust' => self::clean($fields['Trust']),
            'demo_keyword' => $demoKeyword,
            'faq_picks' => self::faqPicksOf($file, $position, $slug, $fields['FAQ']),
        ];

        self::assertRowIsSound($file, $row);

        return $row;
    }

    /**
     * Split one row's labelled runs into the nine slots.
     *
     * @return array<string, string>
     *
     * @throws IndustryCorpusInvalid
     */
    private static function fieldsOf(string $file, int $position, string $slug, string $rest): array
    {
        $pattern = '/\b('.implode('|', self::LABELS).'):\s*/u';

        $parts = preg_split($pattern, $rest, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        // [0] is whatever preceded the first label — empty in a well-formed row.
        array_shift($parts);

        $fields = [];

        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $label = $parts[$i];

            if (array_key_exists($label, $fields)) {
                throw IndustryCorpusInvalid::forRow($file, $position, $slug, "the `{$label}:` slot appears twice.");
            }

            $fields[$label] = $parts[$i + 1];
        }

        $missing = array_values(array_diff(self::LABELS, array_keys($fields)));

        if ($missing !== []) {
            throw IndustryCorpusInvalid::forRow(
                $file,
                $position,
                $slug,
                'the nine-slot grammar is missing `'.implode(':`, `', $missing).':`.'
            );
        }

        return $fields;
    }

    /**
     * One field's rendered text.
     *
     * Three jobs, in order: drop the ` · ` that separated this run from the next
     * label (the split consumes the label, never the separator before it), drop
     * markdown emphasis, and unwrap a value the corpus quotes whole. The unwrap
     * is anchored at both ends deliberately — plenty of trust beats *contain* a
     * quoted line without *being* one, and stripping their quotation marks would
     * make the demo's own words read as ours.
     */
    private static function clean(string $value): string
    {
        $value = (string) preg_replace('/[\s·]+$/u', '', trim($value));
        $value = str_replace('**', '', $value);
        $value = trim($value);

        if (preg_match('/^"(.*)"$/su', $value, $quoted) === 1) {
            $value = $quoted[1];
        }

        return trim($value);
    }

    /**
     * @return list<string>
     *
     * @throws IndustryCorpusInvalid
     */
    private static function trioOf(string $file, int $position, string $slug, string $raw): array
    {
        $trio = array_values(array_filter(array_map(
            static fn (string $part): string => trim((string) preg_replace('/\.$/u', '', trim(str_replace('**', '', $part)))),
            explode('·', (string) preg_replace('/[\s·]+$/u', '', trim($raw))),
        ), static fn (string $part): bool => $part !== ''));

        if (count($trio) !== 3) {
            throw IndustryCorpusInvalid::forRow(
                $file,
                $position,
                $slug,
                'the trio splits into '.count($trio).' values on `·`, and the grammar is exactly three.'
            );
        }

        return $trio;
    }

    /**
     * @return list<int>
     *
     * @throws IndustryCorpusInvalid
     */
    private static function faqPicksOf(string $file, int $position, string $slug, string $raw): array
    {
        preg_match_all('/\d+/', $raw, $matches);

        $picks = array_map(intval(...), $matches[0]);

        if ($picks === [] || count($picks) !== count(array_unique($picks))) {
            throw IndustryCorpusInvalid::forRow(
                $file,
                $position,
                $slug,
                'the FAQ picks are `'.trim($raw).'`, and the grammar is distinct question numbers.'
            );
        }

        return $picks;
    }

    /**
     * @throws IndustryCorpusInvalid
     */
    private static function demoKeywordOf(string $file, int $position, string $slug, string $raw): string
    {
        if (preg_match('/\*\*([A-Z][A-Z0-9]*)\*\*/u', $raw, $matches) !== 1) {
            throw IndustryCorpusInvalid::forRow(
                $file,
                $position,
                $slug,
                'the door carries no `**KEYWORD**`: "'.trim($raw).'".'
            );
        }

        return $matches[1];
    }

    /**
     * @throws IndustryCorpusInvalid
     */
    private static function familyOf(string $file, int $position, string $slug, string $door): IndustryFamily
    {
        if (preg_match('~/demo/([a-z]+)~u', $door, $matches) === 1) {
            $family = IndustryFamily::tryFrom($matches[1]);

            if (! $family instanceof IndustryFamily) {
                throw IndustryCorpusInvalid::forRow(
                    $file,
                    $position,
                    $slug,
                    "its door names `/demo/{$matches[1]}`, which is not one of the six families."
                );
            }

            return $family;
        }

        foreach (self::FAMILY_RANGES as [$from, $to, $family]) {
            if ($position >= $from && $position <= $to) {
                return IndustryFamily::from($family);
            }
        }

        throw IndustryCorpusInvalid::forRow(
            $file,
            $position,
            $slug,
            'it carries no `/demo/{family}` door and sits in no range this file\'s header declares, '
            .'so nothing says which family it belongs to.'
        );
    }

    /**
     * @param  array{
     *     position: int, slug: string, family: IndustryFamily, h1: string, title: string,
     *     meta_desc: string, hook: string, beat: string, trio: list<string>, trust: string,
     *     demo_keyword: string, faq_picks: list<int>,
     * }  $row
     *
     * @throws IndustryCorpusInvalid
     */
    private static function assertRowIsSound(string $file, array $row): void
    {
        $lengths = [
            'h1' => self::H1_MAX,
            'title' => self::TITLE_MAX,
            'meta_desc' => self::META_DESC_MAX,
        ];

        foreach ($lengths as $field => $max) {
            $length = mb_strlen($row[$field]);

            if ($length === 0) {
                throw IndustryCorpusInvalid::forRow($file, $row['position'], $row['slug'], "its `{$field}` is empty.");
            }

            if ($length > $max) {
                throw IndustryCorpusInvalid::forRow(
                    $file,
                    $row['position'],
                    $row['slug'],
                    "its `{$field}` is {$length} characters and the limit is {$max}: \"{$row[$field]}\"."
                );
            }
        }

        foreach (['hook', 'beat', 'trust'] as $field) {
            if ($row[$field] === '') {
                throw IndustryCorpusInvalid::forRow($file, $row['position'], $row['slug'], "its `{$field}` is empty.");
            }
        }

        // ⚠️ THE CLAIM LAW, MADE MECHANICAL — PIII-64A's header: *"Every price
        // [DATA]. Zero statistics"*, and CC-2 §3's scoped grep for the same thing
        // one directory over. An unbound `[DATA]` slot is the sharper half: it is
        // the corpus saying *a price goes here* about a price no registry row can
        // supply — a landscaper's own range is not ours to know — so it would
        // reach a public page as the literal characters `$[DATA]`.
        //
        // ⚠️ IT MATCHED ON THE DAY IT WAS WRITTEN (decision 5222) and matches
        // nothing now, which is the right way round: 256 asks for the lint that
        // can fail today, and this one did.
        foreach (['h1', 'title', 'meta_desc', 'hook', 'beat', 'trust'] as $field) {
            self::assertCarriesNoUnboundClaim($file, $row, $field, $row[$field]);
        }

        foreach ($row['trio'] as $value) {
            self::assertCarriesNoUnboundClaim($file, $row, 'trio', $value);
        }
    }

    /**
     * @param  array{position: int, slug: string, ...}  $row
     *
     * @throws IndustryCorpusInvalid
     */
    private static function assertCarriesNoUnboundClaim(string $file, array $row, string $field, string $value): void
    {
        if (str_contains($value, '[DATA]')) {
            throw IndustryCorpusInvalid::forRow(
                $file,
                $row['position'],
                $row['slug'],
                "its `{$field}` carries an unbound `[DATA]` slot and would render those characters to a "
                ."reader: \"{$value}\"."
            );
        }

        if (preg_match('/\$\s*\d|\d\s*%/u', $value) === 1) {
            throw IndustryCorpusInvalid::forRow(
                $file,
                $row['position'],
                $row['slug'],
                "its `{$field}` writes a price or a percentage as a literal: \"{$value}\"."
            );
        }
    }

    /**
     * @param  list<array{position: int, slug: string, h1: string, title: string, meta_desc: string, demo_keyword: string, ...}>  $rows
     *
     * @throws IndustryCorpusInvalid
     */
    private static function assertCorpusIsWhole(array $rows): void
    {
        if (count($rows) !== self::EXPECTED_ROWS) {
            throw IndustryCorpusInvalid::forCorpus(
                'parsed '.count($rows).' rows and the hundred is '.self::EXPECTED_ROWS.'.'
            );
        }

        $positions = array_column($rows, 'position');
        sort($positions);

        if ($positions !== range(1, self::EXPECTED_ROWS)) {
            throw IndustryCorpusInvalid::forCorpus(
                'the positions are not 1–'.self::EXPECTED_ROWS.' exactly once each, which is the order the '
                .'hub lists them in.'
            );
        }

        // ⚠️ THE LAST THREE ARE PIII-72 §A4's UNIQUENESS LAW, CHECKED HERE AS
        // WELL AS IN THE ARCHITECTURE SUITE. The lint reads the seeded table and
        // is the build-failing one; this reads the authored files, so a duplicate
        // is named against the file and row that wrote it rather than against a
        // database row somebody then has to trace back.
        foreach (['slug', 'demo_keyword', 'h1', 'title', 'meta_desc'] as $field) {
            /** @var list<string> $values */
            $values = array_column($rows, $field);

            $duplicates = array_values(array_unique(array_diff_assoc($values, array_unique($values))));

            if ($duplicates !== []) {
                throw IndustryCorpusInvalid::forCorpus(
                    "two rows share a `{$field}`: \"".implode('", "', $duplicates).'".'
                );
            }
        }
    }
}
