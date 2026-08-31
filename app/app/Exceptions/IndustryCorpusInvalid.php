<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\IndustryPageManifest;
use RuntimeException;

/**
 * The authored industry corpus does not satisfy the parse contract — CC-3 §2:
 * *"A parse that can't satisfy the contract halts with the offending row named —
 * never a silent skip."*
 *
 * ⚠️ **ITS WHOLE VALUE IS THE MESSAGE.** A seeder that skipped a malformed row
 * would leave 99 pages, a hub missing one card, and nothing anywhere saying
 * which — so every construction path below names the file and the row it choked
 * on, and {@see self::forRow()} is the only spelling a per-row check may use.
 * {@see IndustryPageManifest} is the only thrower.
 */
final class IndustryCorpusInvalid extends RuntimeException
{
    /**
     * A fault attributable to one authored row.
     */
    public static function forRow(string $file, int $position, string $slug, string $problem): self
    {
        return new self(sprintf(
            'Industry row %02d (/industries/%s) in %s: %s',
            $position,
            $slug,
            $file,
            $problem,
        ));
    }

    /**
     * A fault attributable to one authored file rather than to a row in it.
     */
    public static function forFile(string $file, string $problem): self
    {
        return new self("Industry source file {$file}: {$problem}");
    }

    /**
     * A fault only visible across the whole hundred — a duplicate, or a count.
     */
    public static function forCorpus(string $problem): self
    {
        return new self("The industry corpus: {$problem}");
    }
}
