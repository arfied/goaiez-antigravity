<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BusinessFact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<BusinessFact>
 */
final class BusinessFactFactory extends Factory
{
    protected $model = BusinessFact::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->sentence(),
            'source' => 'crawl',
            'verified_by_owner' => false,
            'updated_at' => now(),
        ];
    }
}
