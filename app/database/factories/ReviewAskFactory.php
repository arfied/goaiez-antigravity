<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\ReviewAsk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<ReviewAsk>
 */
final class ReviewAskFactory extends Factory
{
    protected $model = ReviewAsk::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'offered_at' => now(),
        ];
    }
}
