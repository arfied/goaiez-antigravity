<?php

declare(strict_types=1);

namespace App\Services\Assistant;

/**
 * What we managed to get out of an uploaded price sheet — T176 P5, §2.4.
 *
 * ⚠️ **THE COUNT OF LINES WE COULD NOT READ IS PART OF THE ANSWER, NOT A LOG
 * LINE.** A parser that quietly returns the eleven rows it understood, out of a
 * file with fourteen prices in it, hands an owner a list that looks complete —
 * and the three missing jobs are then the ones the assistant takes a message
 * about while the owner believes they are priced. Saying "we read eleven, and
 * three lines we could not make sense of" costs one sentence and is the
 * difference between a tool and a trap.
 */
final readonly class PriceSheetReading
{
    /**
     * @param  list<array{label: string, minorUnits: int, maxCents: ?int}>  $rows
     *                                                                             What the parser is confident about, in the order the file gave them.
     * @param  int  $unreadableLines  Lines that carried something priced-looking
     *                                and could not be read as one price. Blank
     *                                lines and prose are **not** counted — a
     *                                heading is not a failure.
     */
    public function __construct(
        public array $rows,
        public int $unreadableLines,
    ) {}
}
