<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Services\Fetch\FetchResult;

/**
 * What one look at a tenant's website found, including the looks that never
 * happened.
 *
 * ⛔ **`looked` IS A SEPARATE FACT FROM THE TWO FINDINGS AND COLLAPSING IT IS
 * THE DEFECT THIS SHAPE EXISTS TO PREVENT** (`BUILD-PLAN` §2.11.2, decision
 * 229). *"We looked and this is not WordPress"* and *"we have never been able to
 * look"* are two different things a tenant is told, and a bare
 * `wordpress: false` is both of them. The gateway makes the same distinction one
 * layer down — {@see FetchResult::wasRefused()} — and for
 * the same reason.
 *
 * `detail` is a short code and never a sentence, matching
 * {@see AdapterOutcome} and `FetchResult::$refusalReason`: these end up in logs.
 */
final readonly class SiteProbeReading
{
    private function __construct(
        public bool $looked,
        public bool $wordpress,
        public bool $cloudflare,
        public string $detail,
    ) {}

    public static function found(bool $wordpress, bool $cloudflare): self
    {
        return new self(true, $wordpress, $cloudflare, 'scanned');
    }

    /**
     * Nothing was learned, and the reason is worth keeping.
     *
     * ⚠️ **BOTH FINDINGS ARE FALSE HERE AND NEITHER MEANS "NO"** — that is what
     * `looked` is for, and why nothing persists on this arm.
     */
    public static function didNotLook(string $detail): self
    {
        return new self(false, false, false, $detail);
    }
}
