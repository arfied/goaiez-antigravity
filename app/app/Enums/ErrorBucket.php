<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of two piles a carrier DLR failure falls into — doc `51` §4.2, §10.
 *
 * Two buckets, not five. `43` §10.1's `error_class` (`carrier_filter`,
 * `absent_subscriber`, `invalid_number`, `rejected`, `other`) is superseded by
 * doc `51` here (D-250: "absorbs `SharedPoolHealthProbe` scoring into one
 * service") — the finer taxonomy is still useful for a human reading a failure
 * in the Ops board (phase 3+, not built here), but the *score* only ever asks
 * one question: does this failure implicate the number, or the destination.
 *
 * ⚠️ **DELIBERATELY NO `{@see}` TO `App\Models\DlrErrorBucket` OR
 * `App\Services\Sms\NumberHealthService` HERE.** Pint promotes a `{@see}` tag
 * into a real `use` statement, and `MessagingTest`'s "one scorer" chokepoint
 * confines both of those names to `NumberHealthService.php` alone — a doc tag
 * on this enum would trip the lint it has nothing to do with breaking. Named
 * in plain text (`DlrErrorBucket`, `NumberHealthService`) instead, which reads
 * identically and imports nothing.
 */
enum ErrorBucket: string
{
    /**
     * Carrier-block/spam classes — the signal that matters. A run of these is
     * what a filter or a spam-trap actually looks like from here, and it is
     * what `NumberHealthService` treats as evidence against the *number*.
     *
     * ⛔ **THIS CASE MATCHED NOTHING IN `dlr_error_buckets` UNTIL 2026-08-14,
     * WHICH MADE DOC 51 §5.2's FIRST QUARANTINE TRIGGER UNABLE TO FIRE AT ALL**
     * (1650). The seed shipped with one row and it was an `Other` one, so
     * `failed_filtered` was permanently zero in production while every test
     * passed on synthetic error names. **It has occupants now**, two of them,
     * taken from Infobip's own published error table on 2026-08-14 —
     * `EC_REJECTED_SPAM_BY_OPERATOR` and `EC_BLACKLISTED_SENDERADDRESS`. The
     * seeding migration carries the vendor's sentence for each, the six names
     * it refused, and the one genuinely close call it left unmapped.
     *
     * ⚠️ **TWO IS THIN AND THAT IS THE POINT, NOT AN OVERSIGHT.** A mapping
     * padded until it looks complete is one padded past what the vendor's page
     * actually says (511's shape), and every name added here can quarantine a
     * number — on the shared Lane A pool number, every text on the platform at
     * once. Add a row only with the vendor's own sentence for it in hand.
     */
    case Filtered = 'filtered';

    /**
     * Invalid number, handset off — noise that must not poison the score. A
     * failure here is a fact about the *destination*, not about the number
     * that tried to reach it, and doc `51` §4.2 is explicit that it must not
     * be allowed to condemn one.
     *
     * ⚠️ **THE DEFAULT FOR ANYTHING UNMAPPED.** An error name this platform has
     * never seen, or has not verified against Infobip's own published
     * vocabulary, lands here — never in {@see self::Filtered} — because the
     * cost of under-reacting to a real filter is a slower quarantine, and the
     * cost of over-reacting to an unrecognised name is condemning a number for
     * noise. **The default must be the bucket that does not condemn a
     * number** — this file's own rule rather than a quotation, because no
     * governing document states it in those words.
     */
    case Other = 'other';
}
