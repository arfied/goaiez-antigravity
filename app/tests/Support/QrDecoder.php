<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\Feedback\QrCodeSvg;
use BaconQrCode\Common\FormatInformation;
use BaconQrCode\Common\Mode;
use BaconQrCode\Common\Version;
use BaconQrCode\Encoder\MaskUtil;
use RuntimeException;

/**
 * Reads a rendered QR code back to the text it encodes.
 *
 * ⛔ **THIS EXISTS BECAUSE AN IMAGE THAT RENDERS IS NOT AN IMAGE THAT IS
 * CORRECT.** The load-bearing claim about a printed sign is that it takes a
 * customer to *this* location's `/f/{slug}` page and not to another location's,
 * and no assertion about markup can say that: an `<svg>` is present, an
 * `aria-label` reads plausibly, and the symbol still resolves to the wrong shop.
 * Every path from the URL to the modules — encoder, error-correction level,
 * mask, quiet zone, our own drawing — sits between the two, and only a decode
 * crosses all of it.
 *
 * ⚠️ **THE VENDOR SHIPS NO DECODER, AND THIS IS NOT ONE EITHER — IT IS THE HALF
 * OF ONE A TEST NEEDS.** `bacon/bacon-qr-code` generates only. A camera's job is
 * mostly the part omitted here: finding a symbol in a photograph, correcting
 * perspective, thresholding, and repairing damage with Reed-Solomon. This starts
 * from an exact module matrix, so it needs none of that — what it does do is
 * everything after the modules are known, exactly as a scanner does it:
 *
 *   1. read the format information from the matrix (both copies, BCH-corrected)
 *      for the error-correction level and mask pattern;
 *   2. undo that mask;
 *   3. read the codewords in ISO/IEC 18004 §8.7.3's two-column zigzag, skipping
 *      the function patterns;
 *   4. de-interleave them into blocks using the version's own block table;
 *   5. parse the bitstream — mode, character count, bytes.
 *
 * ⚠️ **THE TABLES ARE THE VENDOR'S AND THAT IS THE POINT.** `Version`,
 * `FormatInformation`, `MaskUtil` and `Mode` are the same classes the encoder
 * used, so the eight mask formulas, the forty-one block layouts and the format
 * BCH code are not retyped here to drift from the ones under test. What this
 * class contributes is the traversal, which is the part with no table.
 *
 * ⛔ **IT DOES NOT ERROR-CORRECT, DELIBERATELY.** A decoder that repaired
 * damage would also repair *our* bugs: a renderer that dropped a module in the
 * data region would be silently fixed and the test would pass on a symbol that
 * had been corrupted. Reading the data codewords straight means any defect in
 * the drawing shows up as a wrong string or a refusal.
 *
 * ⚠️ **WHAT IT CANNOT SEE, SAID RATHER THAN IMPLIED** (352, 397, 565). It reads
 * only the data region, so **damage to a finder pattern, a separator, the
 * timing lines or an alignment pattern is invisible to it** — a mutation
 * deleting one of those modules from the renderer left every test in
 * `ReviewSignTest` green, while the same mutation inside the data region turned
 * four of them red. That is not the decoder being wrong: those patterns exist so
 * a *camera* can find and square the symbol in a photograph, and this class is
 * handed an exact matrix and needs none of them. It does mean the claim these
 * tests make is **"the symbol carries the right bytes"**, and never **"a phone
 * would read this off a printed card"** — the second is a physical property of
 * ink, contrast and size, and nothing in this suite can reach it.
 */
final class QrDecoder
{
    /**
     * The one path shape {@see QrCodeSvg} emits: an
     * origin, a horizontal run of dark modules, one module of height, closed.
     *
     * ⚠️ **PINNED TO THAT SPELLING RATHER THAN TO SVG IN GENERAL.** A tolerant
     * path parser would quietly match nothing the day the renderer changed and
     * this class would decode an empty matrix — 256's vacuity with a QR code
     * attached. {@see self::matrixFromSvg()} therefore also refuses a document
     * it found no runs in.
     */
    private const string RUN = '/M(\d+) (\d+)h(\d+)v1h-(\d+)z/';

    /**
     * The text a rendered `<svg>` encodes.
     */
    public static function decodeSvg(string $svg, int $quietZone): string
    {
        return self::decodeMatrix(self::matrixFromSvg($svg, $quietZone));
    }

    /**
     * The module matrix an `<svg>` draws, with its quiet zone stripped.
     *
     * @return list<list<bool>> indexed `[$row][$column]`
     */
    public static function matrixFromSvg(string $svg, int $quietZone): array
    {
        if (preg_match('/viewBox="0 0 (\d+) \d+"/', $svg, $box) !== 1) {
            throw new RuntimeException('That SVG has no square viewBox, so it is not a QR code this class drew.');
        }

        $side = (int) $box[1];
        $dimension = $side - ($quietZone * 2);

        if ($dimension < 21 || ($dimension - 17) % 4 !== 0) {
            throw new RuntimeException("A {$dimension}-module symbol is not a legal QR version.");
        }

        $matrix = array_fill(0, $dimension, array_fill(0, $dimension, false));

        $found = preg_match_all(self::RUN, $svg, $runs, PREG_SET_ORDER);

        if ($found === 0) {
            throw new RuntimeException(
                'No dark runs matched. QrCodeSvg draws every module as `M{x} {y}h{w}v1h-{w}z`; '
                .'if that has changed, this decoder has to change with it rather than silently '
                .'returning an empty matrix.',
            );
        }

        foreach ($runs as $run) {
            $x = (int) $run[1] - $quietZone;
            $y = (int) $run[2] - $quietZone;
            $width = (int) $run[3];

            for ($i = 0; $i < $width; $i++) {
                $matrix[$y][$x + $i] = true;
            }
        }

        return $matrix;
    }

    /**
     * @param  list<list<bool>>  $matrix  indexed `[$row][$column]`
     */
    public static function decodeMatrix(array $matrix): string
    {
        $dimension = count($matrix);
        $version = Version::getProvisionalVersionForDimension($dimension);

        $format = self::readFormatInformation($matrix, $dimension);
        $mask = $format->getDataMask();

        $codewords = self::readCodewords($matrix, $dimension, $version, $mask);
        $data = self::deinterleave($codewords, $version, $format);

        return self::readBitstream($data, $version);
    }

    /**
     * The error-correction level and mask pattern, from both stored copies.
     *
     * ISO/IEC 18004 §8.9: fifteen bits, written twice — once around the
     * top-left finder and once split between the other two — so that a symbol
     * with one damaged corner still declares how to read itself.
     * {@see FormatInformation::decodeFormatInformation()} is the vendor's own
     * BCH decode, and it is handed both copies exactly as `BitMatrixParser`
     * does.
     *
     * @param  list<list<bool>>  $matrix
     */
    private static function readFormatInformation(array $matrix, int $dimension): FormatInformation
    {
        $copy = static fn (int $x, int $y, int $bits): int => $matrix[$y][$x] ? ($bits << 1) | 1 : $bits << 1;

        $first = 0;

        for ($i = 0; $i < 6; $i++) {
            $first = $copy($i, 8, $first);
        }

        $first = $copy(7, 8, $first);
        $first = $copy(8, 8, $first);
        $first = $copy(8, 7, $first);

        for ($j = 5; $j >= 0; $j--) {
            $first = $copy(8, $j, $first);
        }

        $second = 0;

        for ($j = $dimension - 1; $j >= $dimension - 7; $j--) {
            $second = $copy(8, $j, $second);
        }

        for ($i = $dimension - 8; $i < $dimension; $i++) {
            $second = $copy($i, 8, $second);
        }

        $format = FormatInformation::decodeFormatInformation($first, $second);

        if ($format === null) {
            throw new RuntimeException('Neither copy of the format information decodes, so this is not a readable symbol.');
        }

        return $format;
    }

    /**
     * The codewords, in the order a scanner reads them.
     *
     * ISO/IEC 18004 §8.7.3: two module columns at a time from the bottom-right
     * corner leftwards, alternating up the symbol and down it, right module
     * before left, skipping every function pattern — and skipping column 6
     * entirely, which is the vertical timing pattern.
     *
     * @param  list<list<bool>>  $matrix
     * @return list<int>
     */
    private static function readCodewords(array $matrix, int $dimension, Version $version, int $mask): array
    {
        $functions = $version->buildFunctionPattern();

        $codewords = [];
        $current = 0;
        $bitsRead = 0;
        $readingUp = true;

        for ($j = $dimension - 1; $j > 0; $j -= 2) {
            if ($j === 6) {
                $j--;
            }

            for ($count = 0; $count < $dimension; $count++) {
                $row = $readingUp ? $dimension - 1 - $count : $count;

                for ($column = 0; $column < 2; $column++) {
                    $x = $j - $column;

                    if ($functions->get($x, $row)) {
                        continue;
                    }

                    $bit = $matrix[$row][$x];

                    if (MaskUtil::getDataMaskBit($mask, $x, $row)) {
                        $bit = ! $bit;
                    }

                    $current = ($current << 1) | ($bit ? 1 : 0);
                    $bitsRead++;

                    if ($bitsRead === 8) {
                        $codewords[] = $current;
                        $current = 0;
                        $bitsRead = 0;
                    }
                }
            }

            $readingUp = ! $readingUp;
        }

        return $codewords;
    }

    /**
     * The data codewords, un-interleaved.
     *
     * ISO/IEC 18004 §8.6: a symbol's data is split into blocks and the blocks
     * are written round-robin, so one burst of damage is spread across all of
     * them rather than destroying one. Undoing it needs the block layout, which
     * is the version's own table for this error-correction level — the vendor's,
     * so it cannot drift from the one the encoder used.
     *
     * The error-correction codewords that follow are read and dropped: see the
     * class docblock on why nothing here repairs anything.
     *
     * @param  list<int>  $codewords
     * @return list<int>
     */
    private static function deinterleave(array $codewords, Version $version, FormatInformation $format): array
    {
        $blocks = [];

        foreach ($version->getEcBlocksForLevel($format->getErrorCorrectionLevel())->getEcBlocks() as $ecBlock) {
            for ($n = 0; $n < $ecBlock->getCount(); $n++) {
                $blocks[] = ['capacity' => $ecBlock->getDataCodewords(), 'bytes' => []];
            }
        }

        $longest = 0;

        foreach ($blocks as $block) {
            $longest = max($longest, $block['capacity']);
        }

        $offset = 0;

        for ($i = 0; $i < $longest; $i++) {
            foreach ($blocks as $index => $block) {
                if ($i >= $block['capacity']) {
                    continue;
                }

                if (! array_key_exists($offset, $codewords)) {
                    throw new RuntimeException('The symbol holds fewer codewords than its version claims.');
                }

                $blocks[$index]['bytes'][] = $codewords[$offset];
                $offset++;
            }
        }

        $data = [];

        foreach ($blocks as $block) {
            foreach ($block['bytes'] as $byte) {
                $data[] = $byte;
            }
        }

        return $data;
    }

    /**
     * The text those codewords spell.
     *
     * ⚠️ **BYTE MODE ONLY, AND ANYTHING ELSE IS A REFUSAL RATHER THAN A GUESS.**
     * `QrCodeSvg` encodes URLs, which the encoder puts in byte mode; a numeric
     * or alphanumeric segment turning up here would mean it had chosen
     * differently, and a decoder that quietly handled it would hide that.
     *
     * @param  list<int>  $data
     */
    private static function readBitstream(array $data, Version $version): string
    {
        $bits = '';

        foreach ($data as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $position = 0;
        $length = strlen($bits);

        $read = static function (int $count) use (&$position, $bits, $length): int {
            if ($position + $count > $length) {
                throw new RuntimeException('The bitstream ends inside a field, so this symbol is truncated.');
            }

            $chunk = substr($bits, $position, $count);
            $position += $count;

            return (int) bindec($chunk);
        };

        $text = '';

        while ($position + 4 <= $length) {
            $mode = $read(4);

            // The terminator, or the zero padding that follows it.
            if ($mode === Mode::TERMINATOR()->getBits()) {
                break;
            }

            if ($mode !== Mode::BYTE()->getBits()) {
                throw new RuntimeException("Mode {$mode} is not byte mode, and this decoder reads no other.");
            }

            $characters = $read(Mode::BYTE()->getCharacterCountBits($version));

            for ($i = 0; $i < $characters; $i++) {
                $text .= chr($read(8));
            }
        }

        return $text;
    }
}
