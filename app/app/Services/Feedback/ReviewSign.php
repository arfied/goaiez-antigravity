<?php

declare(strict_types=1);

namespace App\Services\Feedback;

/**
 * One location's printable review sign — everything the panel prints, and
 * nothing else.
 *
 * ⚠️ **A VALUE OBJECT RATHER THAN AN ARRAY SHAPE, BECAUSE ONE OF THESE FIELDS
 * IS SAFE HTML AND THREE ARE NOT.** `$svg` is markup this application built and
 * is rendered unescaped; `$locationName` is tenant-authored and must be escaped
 * like any other string. A named type is what stops the two being handled
 * alike in a template written months from now.
 *
 * ⛔ **IT CARRIES NO CUSTOMER DATA AND MUST NOT ACQUIRE ANY.** A sign is about a
 * location and an address, and it is rendered on a screen a support agent can
 * be looking at over an owner's shoulder.
 */
final readonly class ReviewSign
{
    public function __construct(
        /**
         * The location's own name, as its owner wrote it.
         */
        public string $locationName,

        /**
         * The absolute `/f/{slug}` address the code encodes.
         *
         * ⚠️ **THIS IS WHAT IS SCANNED, AND IT IS THE FEEDBACK PAGE RATHER THAN
         * A REVIEW SITE.** `24` §2.3 routes on the rating, *after* it is given;
         * a sign pointing straight at Google would take that decision away from
         * the customer and the tenant both, and decision 113 says a destination
         * click is never a review anyway.
         */
        public string $url,

        /**
         * The same address without its scheme, for printing.
         *
         * `https://` is noise to somebody typing an address off a card, and the
         * printed text is the accessible path for a person who cannot scan —
         * the argument the 2FA screen makes for the typed secret beside its QR.
         */
        public string $displayUrl,

        /**
         * The QR itself, as an `<svg>` document.
         *
         * ⛔ **SAFE HTML BY CONSTRUCTION, NOT BY TRUST.** Every character comes
         * from {@see QrCodeSvg}: integers from the encoder, and one accessible
         * name that class escapes itself. No tenant string reaches it.
         */
        public string $svg,
    ) {}
}
