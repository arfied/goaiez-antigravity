<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BoostScoreHistory;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<BoostScoreHistory>
 */
final class BoostScoreHistoryFactory extends Factory
{
    protected $model = BoostScoreHistory::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'score' => fake()->numberBetween(0, 100),
            'recorded_at' => now(),
        ];
    }
}
