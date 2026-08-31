<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A rung on the fetch ladder (`40` §6.1).
 *
 * ONLY F0 IS BUILT, DELIBERATELY. `BUILD-PLAN` §2.5.2 slice D: "build the
 * interface and the policy gate now, leave the F1–F3 ladder to the row that
 * needs it". The whole enum is declared anyway, because the *policy* — which
 * sources may reach which tier — is what slice D exists to enforce, and a
 * ceiling of `render_ok` has to be expressible before the renderer exists. A
 * ceiling that could only name tiers we had built would need rewriting the day
 * F1 lands, which is exactly when nobody wants to be re-deriving policy.
 *
 * F1 and F2 have no implementation. Asking the gateway for one is a programming
 * error and says so, rather than silently falling back to F0 — a silent
 * downgrade would make "this page needs JS" indistinguishable from "this page is
 * empty", and `40` §6.4 turns that difference into what the owner is told.
 */
enum FetchTier: string
{
    /** Direct fetch, honest user agent. The only tier slice D implements. */
    case F0 = 'f0';

    /** Headless render for JS-required pages. Not built. */
    case F1 = 'f1';

    /** Rotating residential/ISP proxy pool. Not built, and policy-gated. */
    case F2 = 'f2';

    /**
     * Stop honestly — mark the source degraded, fall back per capability, never
     * fabricate and never render stale as fresh. Not a fetch; the absence of one.
     */
    case F3 = 'f3';

    public function rank(): int
    {
        return match ($this) {
            self::F0 => 0,
            self::F1 => 1,
            self::F2 => 2,
            self::F3 => 3,
        };
    }

    /**
     * Whether this project has an implementation for the tier today.
     */
    public function isImplemented(): bool
    {
        return $this === self::F0;
    }
}
