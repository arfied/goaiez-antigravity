<?php

declare(strict_types=1);

namespace App\Services\Ops;

/**
 * One webhook endpoint, and whether this install holds what it judges callers
 * with (11640–11651).
 *
 * ⚠️ **A ROW ABOUT A DEPLOYMENT, NOT ABOUT THE TREE.** Every field but the two
 * class names is read off the running install — the encrypted store and the
 * live configuration — which is the only thing that can answer this. `CLAUDE.md`'s
 * *a seed is not a deployment* is exactly this question, one vendor at a time.
 */
final readonly class WebhookMaterialLine
{
    /**
     * @param  string  $uri  the route as the router holds it, e.g. `webhooks/ses`
     * @param  string  $verifier  the class that judges this endpoint's callers
     * @param  list<string>  $present  material names this install has
     * @param  list<string>  $missing  material names it does not
     */
    public function __construct(
        public string $uri,
        public string $verifier,
        public array $present,
        public array $missing,
    ) {}

    /**
     * Whether this endpoint could judge a delivery at all today.
     *
     * ⛔ **"COULD JUDGE" IS NOT "WOULD ACCEPT".** A wrong secret, a rotated one,
     * a topic ARN for the wrong account and a signature scheme this profile does
     * not use all read as ready here, and always will — the only instrument that
     * separates them is a genuine delivery. What this answers is the half that
     * *is* knowable at rest and was being reported by nobody.
     */
    public function isReady(): bool
    {
        return $this->missing === [];
    }
}
