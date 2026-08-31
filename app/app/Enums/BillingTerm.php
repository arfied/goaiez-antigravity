<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which of a plan's two prices a subscription is on (decision 2680).
 *
 * ⚠️ **A TERM IS NOT A PLAN, AND KEEPING THEM APART IS THE WHOLE REASON THIS
 * ENUM EXISTS.** `Plan` names *what* somebody bought — the entitlements
 * `plan_entitlements` is keyed by; this names *how often they pay for it*. The
 * registry has held both prices since CFG1 (`price.monthly_cents` and
 * `price.annual_cents`) and until this slice **only the monthly one had a
 * reader**: both gateways quoted `entitlementCents(Plan::Base,
 * 'price.monthly_cents')` and there was no annual path at all.
 *
 * ⚠️ **THE KEYS ARE HERE RATHER THAN AT EACH CALL SITE.** Two gateways, a
 * pricing screen and a checkout form all need "the price for this term", and a
 * string typed at four call sites is four places for `price.annual_cents` to
 * become `price.annual` on the day somebody adds a fifth.
 *
 * A string cast to a PHP backed enum, never a database enum — `CLAUDE.md`.
 */
enum BillingTerm: string
{
    /** Every `billing.cycle_days` — decision 147's 30 days, not a calendar month. */
    case Monthly = 'monthly';

    /**
     * Once a year, on the anniversary of the first charge.
     *
     * ⚠️ **A CALENDAR YEAR ON BOTH GATEWAYS, WHICH IS NOT WHAT 147 SAYS ABOUT
     * THE MONTHLY CYCLE, AND THE DIFFERENCE IS DELIBERATE.** 147 rejects
     * `interval=month` because a "month" is 28–31 days and drifts with the
     * anchor — twelve cycles a year where the plan sells 12.17. An annual term
     * has no such ambiguity: the anniversary of a date is the same date, which
     * is exactly what "$997/year" means. It is also the only annual form both
     * vendors document — Stripe's own OpenAPI spec caps a recurring interval at
     * "3 years, 36 months, or 156 weeks" and does not enumerate days at that
     * magnitude, and Authorize.Net's reference gives `months` a range of 1–12
     * (both read 2026-08-12).
     */
    case Annual = 'annual';

    /**
     * The `plan_entitlements` key holding this term's price for one location.
     */
    public function priceKey(): string
    {
        return match ($this) {
            self::Monthly => 'price.monthly_cents',
            self::Annual => 'price.annual_cents',
        };
    }

    /**
     * The key holding this term's price for each location beyond the first.
     */
    public function additionalLocationKey(): string
    {
        return match ($this) {
            self::Monthly => 'additional_location.monthly_cents',
            self::Annual => 'additional_location.annual_cents',
        };
    }

    /**
     * Outcome language for a page (`22`): what the person is choosing, not how
     * the billing interval is expressed to a vendor.
     */
    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Annual => 'Yearly',
        };
    }

    /**
     * How often a price on this term falls due, as it reads after an amount (4840).
     *
     * "$179.99 **a month**", "$997 **a year**" — the form a billing page needs and
     * {@see self::label()} cannot give it, because "$997 Yearly" is a table cell
     * rather than a sentence. Here rather than in a template for decision 512's
     * reason applied to words instead of figures: a second page writing its own
     * cadence is how "a month" and "per month" end up on the same site.
     */
    public function cadenceLabel(): string
    {
        return match ($this) {
            self::Monthly => 'a month',
            self::Annual => 'a year',
        };
    }
}
