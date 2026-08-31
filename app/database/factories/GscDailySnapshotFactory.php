<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GscDailySnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default `business_id` or `location_id` — see GscSitePropertyFactory.
 *
 * ⚠️ `is_final` DEFAULTS TRUE HERE AND FALSE IN THE COLUMN, and the mismatch is
 * deliberate. The column's default is the conservative one: a row inserted with
 * no stated freshness is assumed still moving. A fixture's default is the
 * *useful* one: most tests are about settled history, and a suite where every
 * seeded day is unfinalised would exercise only the caveat path and never the
 * ordinary one. Tests about freshness say `notFinal()` out loud.
 *
 * @extends Factory<GscDailySnapshot>
 */
final class GscDailySnapshotFactory extends Factory
{
    protected $model = GscDailySnapshot::class;

    public function definition(): array
    {
        $impressions = fake()->numberBetween(20, 400);

        return [
            'date' => now()->subDays(7)->toDateString(),
            // Clicks are drawn from impressions rather than independently: the
            // table CHECKs `clicks <= impressions`, and a factory that can
            // violate its own table's constraint fails in a way that looks like
            // a bug in whatever test happened to draw the bad pair.
            'clicks' => fake()->numberBetween(0, $impressions),
            'impressions' => $impressions,
            'position' => fake()->randomFloat(4, 1, 60),
            'is_final' => true,
        ];
    }

    public function notFinal(): self
    {
        return $this->state(fn (): array => ['is_final' => false]);
    }
}
