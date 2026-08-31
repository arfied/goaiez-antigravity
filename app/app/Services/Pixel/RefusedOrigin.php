<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use Carbon\CarbonImmutable;

/**
 * One website's worth of refused pixel traffic, as a tenant may be shown it
 * (7802).
 *
 * ⛔ **`$origin` IS A STRING A STRANGER CHOSE.** It is the `Origin` request
 * header, up to 512 bytes of whatever the caller wrote — see the creating
 * migration of `ingest_rejects` and `IngestRejects::record()`. It is escaped by
 * Blade, clipped, and rendered in a mono face as evidence rather than as a
 * sentence this platform is asserting: `Admin\OperatorAlertBoard`'s treatment of
 * the identical value, copied deliberately rather than re-invented.
 *
 * ⚠️ **THREE KINDS OF `$origin` AND ONLY ONE OF THEM IS AN ADDRESS**, which is
 * why {@see self::isAddress()} exists and the view branches on it:
 *
 *  - a **named address** — the ordinary case, and the one a tenant acts on;
 *  - **`null`**, meaning the caller sent no `Origin` header at all (5002), which
 *    means it was not a browser on a page and is a genuinely different
 *    diagnosis;
 *  - {@see IngestRejects::OVERFLOW_ORIGIN}, the bucket every distinct origin
 *    past {@see IngestRejects::ORIGINS_PER_HOUR} is folded into (7710). ⛔ **IT
 *    IS THE LITERAL STRING `(other)` AND RENDERING IT RAW WOULD SHOW A TENANT A
 *    WEBSITE CALLED "(other)"**, so it is named in words instead. The count is
 *    still exact; only the fifty-first name in an hour is lost.
 *
 * ⚠️ **`$lastAt` IS THE LAST SIGHTING, NOT THE FIRST.** It is what lets a fixed
 * problem visibly age rather than sitting on the screen as a present-tense
 * accusation — the copy says *when* traffic was last turned away and promises
 * nothing about now.
 */
final readonly class RefusedOrigin
{
    public function __construct(
        public ?string $origin,
        public int $rejects,
        public CarbonImmutable $lastAt,
    ) {}

    /**
     * Whether `$origin` is a website address a person could go and act on.
     */
    public function isAddress(): bool
    {
        return $this->origin !== null && $this->origin !== IngestRejects::OVERFLOW_ORIGIN;
    }

    /**
     * What to say where there is no address to show.
     *
     * ⚠️ **OUTCOME LANGUAGE (`22`), AND NEITHER SENTENCE ACCUSES ANYBODY.**
     * Both of these arrive on a public endpoint anyone may post to, so the
     * honest reading is *"this was not one of your websites"* rather than
     * *"something is wrong with your install"* — a tenant cannot fix a stranger,
     * and telling them to try would be a null result dressed up as a diagnosis
     * (3084's rule, one screen over).
     */
    public function summary(): string
    {
        return $this->origin === null
            ? 'Visits that did not say which website they came from'
            : 'Visits from many different addresses';
    }
}
