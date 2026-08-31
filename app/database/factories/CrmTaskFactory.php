<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrmTask;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<CrmTask>
 */
final class CrmTaskFactory extends Factory
{
    protected $model = CrmTask::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'title' => fake()->sentence(4),
            'due_at' => fake()->dateTimeBetween('now', '+2 weeks'),
            'status' => 'open',
            'created_at' => now(),
        ];
    }

    /**
     * Completed — the state and its timestamp together, because the CHECK
     * refuses either alone (decision 1323).
     */
    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => 'done',
            'done_at' => now(),
        ]);
    }
}
