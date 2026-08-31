<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AgentNudge;
use App\Models\Conversation;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<AgentNudge>
 */
final class AgentNudgeFactory extends Factory
{
    protected $model = AgentNudge::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'customer_id' => Customer::factory(),
            'armed_at' => now(),
            'armed_at_turns' => 0,
            // Due immediately by default: a fixture that has to wait is the
            // exception, and `->state(['due_at' => …])` says so where it matters.
            'due_at' => now(),
            'expires_at' => now()->addDay(),
        ];
    }
}
