<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory) — and does NOT set messaging_lane: the lane
 * is derived, the column defaults to 'none', and a factory default of
 * anything else would make tests pass against a state production can only
 * reach through a consent record.
 *
 * All personal data is faker output; never a real person's.
 *
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('+1##########'),
            'lifecycle_stage' => 'lead',
            'first_seen_at' => now()->subDays(fake()->numberBetween(1, 90)),
        ];
    }
}
