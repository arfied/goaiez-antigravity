<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WizardStep;
use App\Models\User;
use App\Models\WizardProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tenant-owned — see WizardProgress and docs/DECISIONS.md 177.
 *
 * business_id is deliberately absent: BelongsToTenant fills it from the tenant
 * in context, and a factory that created its own would make a second tenant.
 * user_id still defaults to a new user, which is safe because a user is not a
 * tenant — but pass one explicitly whenever the test cares which.
 *
 * @extends Factory<WizardProgress>
 */
final class WizardProgressFactory extends Factory
{
    protected $model = WizardProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'current_step' => fake()->randomElement(WizardStep::cases()),
            'completed' => false,
            'data' => [],
        ];
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'completed' => true,
            'setup_score' => fake()->numberBetween(60, 100),
        ]);
    }
}
