<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The closed vocabulary L3 will accept as a cohort dimension.
 *
 * ⛔ **THIS IS A GATE, NOT A TAXONOMY, AND READING IT AS A TAXONOMY IS THE
 * MISTAKE IT IS WRITTEN TO PREVENT.** `GOAIEZ_PIXEL_MASTER_BUILD` §5.5 says of
 * L3: *"no `tenant_id`, no raw identifiers, no event rows, **no free text
 * originating from tenant content**"*, and `businesses.vertical` is a plain
 * `string` column. Nothing in this application writes it today (see below), but
 * the thing that eventually will is a wizard question or an importer, and either
 * can put a typed sentence in it. A typed sentence copied into a cross-tenant
 * de-identified layer is the one leak this layer's whole design exists to make
 * impossible — *"Bob's Plumbing of Toledo"* as a cohort key names the business
 * the missing `business_id` was supposed to have hidden.
 *
 * So the vertical is **normalised through this enum before it can enter L3**,
 * and a business whose stored vertical is not one of these cases contributes to
 * nothing. Fail-closed: an unrecognised vertical excludes a tenant from the
 * network rather than opening a bucket for them.
 *
 * ⚠️ **AND THE SAME LIST IS A CHECK CONSTRAINT ON `l3_benchmark_cohort_daily`**,
 * built from these cases in the creating migration, so the refusal is at the
 * database as well as in the code path — `l1_events_data_class_is_never_phi`'s
 * shape. `WarehouseTest` compares the constraint against this enum on every run,
 * because a case added here without a migration is a case the database refuses
 * and the derivation would fail on.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE COLUMN THIS READS HAS NO WRITER, AND THAT IS A FINDING OF THIS SLICE
 * ---------------------------------------------------------------------------
 * `businesses.vertical`, `businesses.size_band` and `businesses.metro_band`
 * shipped with the Stage 0 schema on 2026-07-30 and **only `BusinessFactory`
 * has ever written any of them** (decision 5940). That is `pixel_tenant_id`'s
 * story exactly — `CLAUDE.md`'s own worked example of decision 272 — and
 * `CLAUDE.md` draws the conclusion this enum obeys: *"check for a writer before
 * depending on any table in this schema — and before **designing** against
 * one."*
 *
 * The consequence is stated rather than hidden: **on any real deployment every
 * business's vertical is `null`, so every business is excluded and L3 publishes
 * nothing at all.** `warehouse:benchmark` says so in its output, with a count —
 * a loudly empty layer rather than a silently empty one. What closes it is a
 * writer for the column, which is a taxonomy decision (Google Places types map
 * to *what* list of verticals?) and the owner's to make, not this slice's to
 * invent.
 *
 * ⚠️ **THE CASES BELOW ARE THE ONLY VERTICALS ANYTHING IN THIS TREE HAS EVER
 * WRITTEN** — the five in `BusinessFactory`'s default plus the `medical` its
 * PHI state uses. They are provisional and deliberately few: a short list
 * excludes tenants, and excluding a tenant from a benchmark costs them a
 * comparison, where a wrong bucket costs them a *false* comparison.
 *
 * ⛔ **`medical` IS HERE ON PURPOSE AND MUST NOT BE REMOVED AS TIDYING.** A
 * covered entity is excluded from L3 by classification, at the derivation, and
 * that refusal has to be *falsifiable*: if `medical` were absent from this list
 * the PHI test would pass because the fixture's vertical was unrecognised, not
 * because the tenant handles health information — an outer guard refusing first
 * so the inner one can never be driven (`CLAUDE.md`, 398). The two exclusions
 * are separate and both are driven.
 */
enum BenchmarkVertical: string
{
    case Auto = 'auto';

    case Dental = 'dental';

    case Hvac = 'hvac';

    case Legal = 'legal';

    /**
     * ⛔ Kept so the PHI exclusion at the derivation is falsifiable — see the
     * class docblock. A `medical` business classified `pii` is a benchmark
     * contributor like any other; one classified `phi` is refused for being a
     * covered entity, which is a different rule and is proven separately.
     */
    case Medical = 'medical';

    case Salon = 'salon';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
