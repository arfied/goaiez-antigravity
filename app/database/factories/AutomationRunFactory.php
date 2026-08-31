<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AutomationRun;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A finished, gate-passing run. Does NOT default business_id —
 * BelongsToTenant fills it from the tenant in context (see LocationFactory).
 *
 * @extends Factory<AutomationRun>
 */
final class AutomationRunFactory extends Factory
{
    protected $model = AutomationRun::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'automation_key' => 'review_reply_draft',
            'status' => 'succeeded',
            'input' => ['review_id' => fake()->randomNumber(5)],
            'output' => ['reply_id' => fake()->randomNumber(5)],
            'quality_score' => fake()->numberBetween(70, 100),
            'gate_passed' => true,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ];
    }

    public function failed(): self
    {
        return $this->state(fn (): array => [
            'status' => 'failed',
            'output' => null,
            'gate_passed' => null,
            'error' => 'Provider timeout after 3 attempts.',
        ]);
    }
}
