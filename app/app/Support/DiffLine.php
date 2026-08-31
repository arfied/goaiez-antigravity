<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DiffLineKind;

/**
 * One line of a line-level diff, with the place it sits in each text.
 *
 * A value object rather than an array because the two line numbers are the part
 * a reader actually needs and the part an array shape loses first: a removed
 * line has no number in the draft and an added one has none in the published
 * text, and `null` says that where a `0` would read as the top of the document.
 */
final readonly class DiffLine
{
    public function __construct(
        public DiffLineKind $kind,
        public string $text,
        public ?int $beforeLine,
        public ?int $afterLine,
    ) {}

    /**
     * The line number to print beside this row.
     *
     * The draft's number where there is one, because the draft is the document
     * the reader is editing and the number they will scroll to. A removed line
     * exists only in the published text, so that is the only number it has.
     */
    public function number(): int
    {
        return $this->afterLine ?? $this->beforeLine ?? 0;
    }
}
