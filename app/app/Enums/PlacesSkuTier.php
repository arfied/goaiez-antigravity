<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\PlacesFieldTiers;

/**
 * The five field-mask tiers Google prices a Places (New) request at.
 *
 * ⛔ **ONE TIER IS NOT ONE PRICE, WHICH IS THE WHOLE REASON THIS IS SEPARATE
 * FROM {@see PlacesSku}.** "Enterprise" costs $20.00/1k on Place Details and
 * $35.00/1k on Text Search and on Nearby Search; "Enterprise + Atmosphere"
 * costs $25.00, $40.00 and $40.00 respectively. A tier is therefore a
 * *coordinate*, and only `(family, tier)` names a price — which is exactly the
 * failure decisions 239 and 253 recorded in prose and nothing has enforced
 * since: the Atmosphere figure was read off the **Text Search** row while the
 * call being priced was a **Place Details** call, and every figure in the file
 * was individually plausible.
 *
 * ⚠️ **NOT EVERY FAMILY HAS EVERY TIER, AND THAT IS A FACT ABOUT GOOGLE RATHER
 * THAN AN OMISSION HERE.** Text Search has no mid `Essentials` tier — its
 * address-level fields are **Pro** — and Nearby Search has no `IdsOnly` and no
 * `Essentials` tier at all, because `places.id` itself sits in Nearby Search
 * Pro. {@see PlacesSku::fromFamilyAndTier()} answers `null` for a pair Google
 * does not sell, and {@see PlacesFieldTiers} — which carries the
 * fetch date and the source URLs — is the table that decides which pairs can
 * ever be asked for.
 */
enum PlacesSkuTier: string
{
    case IdsOnly = 'ids_only';

    case Essentials = 'essentials';

    case Pro = 'pro';

    case Enterprise = 'enterprise';

    case EnterpriseAtmosphere = 'enterprise_atmosphere';

    /**
     * Where this tier sits in the billing ladder.
     *
     * Google, verbatim: "You are then billed at the highest SKU applicable to
     * your request. That means if you select fields in both the Essentials and
     * the Pro SKUs, you are billed based on the Pro SKU."
     *
     * An explicit integer rather than an ordering taken from `cases()`, because
     * declaration order is a property of a source file and this is a fact about
     * a price list. The two would part company the first time somebody sorted
     * the cases alphabetically, and nothing would say so.
     */
    public function rank(): int
    {
        return match ($this) {
            self::IdsOnly => 0,
            self::Essentials => 1,
            self::Pro => 2,
            self::Enterprise => 3,
            self::EnterpriseAtmosphere => 4,
        };
    }

    /**
     * The dearer of two tiers, which is the one Google bills.
     */
    public function higherOf(self $other): self
    {
        return $other->rank() > $this->rank() ? $other : $this;
    }
}
