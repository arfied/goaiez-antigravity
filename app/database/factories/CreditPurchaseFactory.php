<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CreditProduct;
use App\Enums\CreditPurchaseStatus;
use App\Enums\CreditTopUpTier;
use App\Enums\PaymentGateway;
use App\Models\CreditPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Does NOT default `business_id` — `BelongsToTenant` fills it from the tenant in
 * context, the same as `CreditLedgerEntryFactory`.
 *
 * ⚠️ **A FACTORY ROW IS A PURCHASE NOBODY CHARGED.** It carries a plausible SKU
 * and a plausible confirmation and **no money moved**, which is right for testing
 * isolation, the CHECK constraints and a settlement handler's refusals — and wrong
 * for testing the funder, which must go through
 * `App\Services\Billing\CreditTopUps` so that the price comes from the registry
 * and the confirmation from a real {@see App\Support\Billing\PurchaseConfirmation}.
 *
 * ⚠️ **THE DEFAULT SKU IS SMS/AUTOMATIC AND ITS FIGURES ARE THE SEEDED ONES.**
 * That is a convenience, not a second source: a test that cares what a SKU costs
 * reads `TopUpCatalog`, and `RegistryTest` is what holds the seeds to
 * `CLAUDE.md`'s table.
 *
 * @extends Factory<CreditPurchase>
 */
final class CreditPurchaseFactory extends Factory
{
    protected $model = CreditPurchase::class;

    // No `@return array<string, mixed>` docblock: Laravel's stub generates one and
    // Larastan rejects it, because the parent declares the narrower
    // `array<model property of CreditPurchase, mixed>`.
    public function definition(): array
    {
        return [
            'product' => CreditProduct::Sms,
            'tier' => CreditTopUpTier::Automatic,
            'gateway' => PaymentGateway::Stripe,
            'status' => CreditPurchaseStatus::Pending,
            'price_cents' => 5_000,
            'currency' => 'USD',
            'units' => 1_000,
            'grant_seed' => 1_000,
            'reference' => 'topup_'.Str::lower(Str::random(20)),
            'gateway_transaction_id' => null,
            'confirmed_actor' => 'user:1',
            'confirmed_amount_cents' => 5_000,
            'confirmation_wording' => 'Charge my card $50.00 for 1,000 text credits.',
            'confirmed_at' => Carbon::now(),
            'authorized_at' => null,
            'credited_at' => null,
            'failure_reason' => null,
        ];
    }
}
