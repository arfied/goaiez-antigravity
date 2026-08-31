<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BillingTerm;
use App\Models\PlanOffer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<PlanOffer>
 */
final class PlanOfferFactory extends Factory
{
    protected $model = PlanOffer::class;

    /**
     * ⛔ **THE DEFAULT IS NOT THE FOUNDER OFFER, AND MUST NOT BECOME IT.**
     *
     * A factory that produced R10's real figures would let a test assert against
     * the founder price without the seed path ever having run — the shape
     * `CLAUDE.md` calls "an isolation test passing perfectly against a table
     * nothing writes". `FounderOfferTest` drives `offers:sync` for that reason.
     * What this builds is a *some other offer* used to exercise the window, the
     * ambiguity refusal and the fallback, and its figures are deliberately not any
     * price this product sells.
     *
     * No `@return array<string, mixed>` docblock: Laravel's stub generates one and
     * Larastan rejects it, because the parent declares the narrower
     * `array<model property of PlanOffer, mixed>`. Every other factory here omits
     * it for the same reason.
     */
    public function definition(): array
    {
        return [
            'key' => 'test-offer',
            'term' => BillingTerm::Monthly->value,
            'price_cents' => 12_345,
            'additional_location_cents' => 6_789,
            'price_currency' => 'USD',
            'instalment_payments' => null,
            'opens_at' => Carbon::now()->subDay(),
            'closes_at' => null,
        ];
    }

    /**
     * An offer whose window has already closed.
     */
    public function closed(): self
    {
        return $this->state(fn (): array => [
            'opens_at' => Carbon::now()->subMonth(),
            'closes_at' => Carbon::now()->subDay(),
        ]);
    }
}
