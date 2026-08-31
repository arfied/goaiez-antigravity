<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\ReviewHubPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<ReviewHubPage>
 */
final class ReviewHubPageFactory extends Factory
{
    protected $model = ReviewHubPage::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'slug' => fake()->unique()->slug(3),
            'title' => fake()->company().' Reviews',
            'is_published' => true,
        ];
    }
}
