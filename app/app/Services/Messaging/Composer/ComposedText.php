<?php

declare(strict_types=1);

namespace App\Services\Messaging\Composer;

use App\Enums\SmsEncoding;

/**
 * One finished reactivation message, with the measurement that proved it fits.
 *
 * ⚠️ **THE LENGTH TRAVELS WITH THE BODY BECAUSE RE-MEASURING IS THE BUG.** A
 * caller that wanted to know how long this was would call `SmsBudget` again, on
 * a body it may have touched in between — and the second answer is the one
 * nobody checked against the law. There is no setter and no `withBody()`: the
 * only way to change the text is to compose again, which measures again.
 *
 * ⚠️ **IT IS NOT AN `OutboundMessage`.** That type carries a `SendPermit` and
 * only `ConsentService` mints one; this carries text and nothing else, so a
 * composer cannot accidentally become a thing that can be sent. The campaign
 * runner puts the two together, having asked the consent gate in between.
 */
final readonly class ComposedText
{
    /**
     * @param  string  $body  The finished text. No templating, no placeholders,
     *                        no truncation left to do.
     * @param  int  $length  In `$encoding`'s own units — septets or UTF-16 code
     *                       units, never bytes and never code points.
     */
    public function __construct(
        public string $body,
        public SmsEncoding $encoding,
        public int $length,
    ) {}

    /**
     * How much of the single-segment budget this message did not use.
     */
    public function headroom(): int
    {
        return $this->encoding->segmentBudget() - $this->length;
    }
}
