<?php

declare(strict_types=1);

namespace App\Services\Sms;

/**
 * One media reference lifted out of an inbound webhook payload.
 *
 * ⚠️ **A URL AND NOTHING ELSE, AND IT IS UNTRUSTED.** The declared content type
 * the carrier sends alongside it is deliberately **not** carried here: it would
 * be a claim by whoever served the file, it is checked against the *response*
 * anyway by {@see InboundMediaFetcher}, and a second copy of it in the payload
 * is a second place for somebody to trust the wrong one. What the platform
 * records is the type it observed.
 *
 * ⛔ **NOR IS THE CARRIER'S FILENAME.** See the creating migration: a
 * sender-chosen filename would be rendered on a staff screen and used to build a
 * download name, which is two injection surfaces bought for a string nothing
 * needs.
 */
final readonly class InboundMediaPart
{
    public function __construct(
        public string $url,
    ) {}
}
