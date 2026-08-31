<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\PlatformPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<PlatformPost>
 */
final class PlatformPostFactory extends Factory
{
    protected $model = PlatformPost::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'platform' => 'google',
            'content' => fake()->paragraph(),
            'status' => 'draft',
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now(),
            'external_id' => fake()->unique()->uuid(),
        ]);
    }
}
