<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Services\Config\DefaultsRegistry;
use App\Services\Knowledge\DocumentText;
use InvalidArgumentException;

/**
 * Reading prices off a file a business uploaded — T176 P5's doc-ingest path.
 *
 * ## ⛔ THIS IS A PARSER AND NOT A MODEL CALL, AND THAT IS THE DESIGN
 *
 * §2.4 asks for "doc ingest with review-before-live". The obvious build is to
 * hand the file to a model and ask it for rows. This does not, for three
 * reasons and in that order:
 *
 *  1. **`29` §2 forbids an LLM call on the synchronous path**, and the whole
 *     value of this screen is that the owner sees the proposals immediately and
 *     confirms them while the file is still in their head. A queued extraction
 *     turns one press into "come back later", which is the shape `Account\
 *     Knowledge` already has and is the one thing that screen's docblock
 *     apologises for.
 *  2. **Every turn costs money** (cost discipline) and a price sheet is the one document
 *     whose structure is regular enough not to need a model at all.
 *  3. **A deterministic parser can be driven red.** "Did the model read column
 *     three?" is not a test.
 *
 * ⚠️ **AND THE REVIEW GATE IS NOT WEAKER FOR IT.** Nothing this class returns is
 * quotable — `PriceBook` writes every row unconfirmed. A model-backed extractor
 * would land in exactly the same place, which is the point of putting the gate
 * on `confirmed_at` rather than on the reader.
 *
 * ## What it reads, and the one thing it refuses to guess
 *
 * A line is a price when it ends in an amount: `Front door lockout - 85`,
 * `Rekey a cylinder: $95.50`, `Van call-out,120`, `Board up a shopfront $150 to
 * $400`. A range may be joined by `-`, an en or em dash, `to`, or `..`.
 *
 * ⛔ **A DIGIT-COMMA-DIGIT ANYWHERE ON THE LINE MAKES IT UNREADABLE, AND THAT IS
 * DELIBERATE RATHER THAN A GAP.** `Rekey all locks $1,200` and `Lockout,85,120`
 * are the same three characters and mean nothing alike — the first is one price
 * of twelve hundred, the second is a range of eighty-five to a hundred and
 * twenty. A parser that picks either reading is right half the time and silently
 * wrong the other half, on a figure the assistant will quote a member of the
 * public. **These lines are counted as unread and shown to the owner**, who
 * types the two of them by hand in less time than it takes to doubt the other
 * forty.
 */
final readonly class PriceSheet
{
    /**
     * The largest price sheet the ingest reads, in kilobytes.
     *
     * Smaller than `KnowledgeUploads::MAX_KILOBYTES` on purpose: this file is
     * parsed **synchronously**, in the request, so its size is a page-load
     * budget rather than a storage one. A quarter-megabyte of plain text is
     * thousands of price lines.
     */
    public const int MAX_KILOBYTES = 256;

    public function maxKilobytes(): int
    {
        return $this->defaults->int('pricebook.sheet.max_kilobytes');
    }

    /**
     * The most rows one upload may propose.
     *
     * ⚠️ **A CEILING RATHER THAN A PAGE.** Past a couple of hundred lines the
     * screen stops being a review and becomes a rubber stamp, which is the one
     * failure mode this whole path exists to avoid.
     */
    public const int MAX_ROWS = 200;

    /**
     * The longest label a row may carry — the column's own width.
     *
     * A line longer than this is prose rather than a priced job, so it is
     * counted as unread rather than truncated: a truncated label is a job
     * description the business did not write, quoted in their name.
     */
    public const int MAX_LABEL_LENGTH = 120;

    public function maxLabelLength(): int
    {
        return $this->defaults->int('assistant.pricesheet.max_label_length');
    }

    public function __construct(
        private DocumentText $text,
        private readonly DefaultsRegistry $defaults,
    ) {}

    /**
     * Read a file's worth of prices.
     *
     * @throws InvalidArgumentException when the bytes are not something we can
     *                                  read words out of at all — the same
     *                                  refusal `DocumentText` gives the Brain,
     *                                  reused rather than re-derived so that a
     *                                  `.txt` full of PDF is refused in both
     *                                  places by one signature test.
     */
    public function read(string $bytes): PriceSheetReading
    {
        $extracted = $this->text->extract($bytes);

        if ($extracted === null) {
            throw new InvalidArgumentException(
                'We cannot read that file. Save your price sheet as a plain text or CSV file and '
                .'try again — one job and its price per line.'
            );
        }

        $rows = [];
        $unreadable = 0;

        foreach (preg_split('/\R/u', $extracted) ?: [] as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                continue;
            }

            $row = $this->parse($trimmed);

            if ($row !== null) {
                if (count($rows) < self::MAX_ROWS) {
                    $rows[] = $row;
                } else {
                    // ⚠️ **THE OVERFLOW IS REPORTED RATHER THAN DROPPED SILENTLY.**
                    // A row past the ceiling is a line that carried a price and did
                    // not become a proposal, which is exactly what the unread count
                    // means — and the alternative is the failure this whole count
                    // exists to prevent: a list that looks complete, with the jobs
                    // past row 200 the ones the assistant takes a message about.
                    $unreadable++;
                }

                continue;
            }

            // ⚠️ ONLY LINES THAT LOOK LIKE THEY WERE MEANT TO CARRY A PRICE ARE
            // COUNTED — the test is that the line *ends* in a digit. A heading
            // ("2026 price list"), an address or a "call for a quote" line is
            // not a failure, and counting one would report a clean file as
            // half-unread and teach the owner to ignore the number.
            if (preg_match('/\d$/u', $trimmed) === 1) {
                $unreadable++;
            }
        }

        return new PriceSheetReading($rows, $unreadable);
    }

    /**
     * @return array{label: string, minorUnits: int, maxCents: ?int}|null
     */
    private function parse(string $line): ?array
    {
        // See the class docblock: `$1,200` and `85,120` are indistinguishable
        // and a parser that picks one is silently wrong half the time.
        if (preg_match('/\d\s*,\s*\d/u', $line) === 1) {
            return null;
        }

        $pattern = '/^(?<label>.*?)[\s:;|,\-–—]+'.$this->amountPattern('low')
            .'(?:\s*(?:-|–|—|to|\.\.)\s*'.$this->amountPattern('high').')?\s*$/u';

        if (preg_match($pattern, $line, $matches) !== 1) {
            return null;
        }

        $label = trim($matches['label']);

        if ($label === '' || mb_strlen($label) > $this->maxLabelLength()) {
            return null;
        }

        // A label with no letter in it is a row number, a date or a total —
        // never a job somebody would ask the price of.
        if (preg_match('/\p{L}/u', $label) !== 1) {
            return null;
        }

        $minorUnits = $this->minorUnits($matches['low'], $matches['lowFraction'] ?? '');

        // ⚠️ NAMED GROUPS THROUGHOUT, BECAUSE THE NUMBERED ONES LIE HERE. A
        // named group still takes a number, so `(?<label>…)` is group 1 and the
        // first amount is group 2 — an off-by-one that reads as a working parser
        // and quotes the wrong figure. PHP also omits an unmatched TRAILING
        // group from `$matches` entirely, which is why the range reads through
        // `??` rather than by position.
        $maxCents = ($matches['high'] ?? '') === ''
            ? null
            : $this->minorUnits($matches['high'], $matches['highFraction'] ?? '');

        if ($maxCents !== null && $maxCents <= $minorUnits) {
            // "$120 to $85" and "$85 to $85" are both a line somebody typed
            // wrong. Unread rather than reordered — reversing it would be this
            // parser deciding what a business meant to charge.
            return null;
        }

        return ['label' => $label, 'minorUnits' => $minorUnits, 'maxCents' => $maxCents];
    }

    /**
     * One amount, under a name of its own.
     *
     * ⚠️ **AN AMOUNT WITH MORE THAN TWO DECIMALS IS NOT AN AMOUNT**, and one
     * with more than seven digits before the point is a phone number or a
     * postcode. Both leave the line unread rather than rounded into a price.
     *
     * The prefix is what keeps the two amounts on a range line distinguishable
     * without PCRE's `J` modifier, which would let two groups share one name and
     * hand back whichever matched last.
     */
    private function amountPattern(string $prefix): string
    {
        return '\$?(?<'.$prefix.'>\d{1,7})(?:\.(?<'.$prefix.'Fraction>\d{1,2}))?';
    }

    /**
     * ⛔ **STRING ARITHMETIC, NEVER `(int) ($amount * 100)`.** `(int) (75.35 *
     * 100)` is **7534** in IEEE-754 — a price a penny short, every time,
     * silently. `Account\AssistantLinks` carries the same note over the same
     * one-line temptation, and 3994 records the mutation that found it.
     */
    private function minorUnits(string $whole, string $fraction): int
    {
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}
