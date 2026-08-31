<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrmNote;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<CrmNote>
 */
final class CrmNoteFactory extends Factory
{
    protected $model = CrmNote::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'created_at' => now(),
        ];
    }
}
