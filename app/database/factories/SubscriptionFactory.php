<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory), and business_id is UNIQUE here: one
 * subscription per business.
 *
 * sms_credits_included stays null — the included allowance is undecided (open
 * question F), and a factory default would leak into assertions as if it were
 * the price sheet.
 *
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'stripe_customer_id' => 'cus_'.fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'stripe_subscription_id' => 'sub_'.fake()->unique()->regexify('[A-Za-z0-9]{14}'),
            'plan' => 'base',
            'status' => 'trialing',
            'sms_credits_included' => null,
            'sms_credits_used' => 0,
            'current_period_end' => now()->addDays(7),
        ];
    }
}
