<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleRatingSnapshot;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A known reading. Does NOT default business_id — BelongsToTenant fills it
 * from the tenant in context ({@see LocationFactory}).
 *
 * @extends Factory<GoogleRatingSnapshot>
 */
final class GoogleRatingSnapshotFactory extends Factory
{
    protected $model = GoogleRatingSnapshot::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'review_count' => $this->faker->numberBetween(0, 200),
            'rating' => $this->faker->randomFloat(1, 1, 5),
            'captured_at' => now(),
        ];
    }

    /**
     * A run whose count was never corroborated — a read that saw a rating but
     * no distinguishable count.
     */
    public function unknownCount(): self
    {
        return $this->state(fn (): array => [
            'review_count' => null,
        ]);
    }
}
