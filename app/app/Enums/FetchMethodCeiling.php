<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The highest fetch tier a source is ever permitted to reach (`40` §6.3).
 *
 * A POLICY, NOT A CAPABILITY. `40` §6.2 draws the line the whole gateway exists
 * to hold: the escalation ladder is there so "transient blocks, datacenter-IP
 * filtering, and flaky sites don't silently break permitted monitoring. It is
 * **not** a tool to defeat prohibitions." The ceiling is where that distinction
 * is written down, per source.
 *
 * `GuidedOnly` is the load-bearing one. Yelp, Facebook and Google-scrape are
 * "ineligible for any fetch tier including F2 — permanently, enforced in the
 * gateway (build-failing test), not in adapter etiquette". Etiquette is what you
 * call a rule nobody can verify; this is the version a test can fail.
 *
 * Raising any source's ceiling requires the counsel-note checkbox on its
 * `fetch_sources` row (`40` §6.2, the D-153 pattern). That is why
 * `counsel_note_ref` exists on the table, and why nothing in code raises a
 * ceiling on its own.
 */
enum FetchMethodCeiling: string
{
    /**
     * Never fetched by us, at any tier, ever.
     *
     * The source is monitored through official or owner-authorised paths where
     * they exist; otherwise the owner gets a guided-fix packet — the wrong
     * value, a deep link, the correct value to paste, and a mark-fixed button.
     */
    case GuidedOnly = 'guided_only';

    /** F0 only: one direct request with an honest user agent. */
    case LightFetch = 'light_fetch';

    /** F0–F1: direct, then a headless render for JS-required pages. */
    case RenderOk = 'render_ok';

    /** F0–F3, proxy tier included. Tenant-owned properties, chiefly. */
    case FullLadder = 'full_ladder';

    /**
     * Whether this ceiling permits any outbound fetch at all.
     */
    public function permitsFetching(): bool
    {
        return $this !== self::GuidedOnly;
    }

    /**
     * The highest tier this ceiling allows.
     *
     * GuidedOnly has no highest tier — it has no tiers — so a caller must ask
     * permitsFetching() first rather than comparing tiers and finding F0 there.
     */
    public function highestTier(): ?FetchTier
    {
        return match ($this) {
            self::GuidedOnly => null,
            self::LightFetch => FetchTier::F0,
            self::RenderOk => FetchTier::F1,
            self::FullLadder => FetchTier::F2,
        };
    }

    public function permits(FetchTier $tier): bool
    {
        $highest = $this->highestTier();

        return $highest !== null && $tier->rank() <= $highest->rank();
    }
}
