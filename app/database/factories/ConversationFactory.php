<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'channel' => fake()->randomElement(['chat', 'sms', 'email']),
            'subject' => fake()->sentence(4),
            'status' => 'new',
            'priority' => 'normal',
            'is_bot_handled' => true,
            // Consent logged by default: a fixture without it models a thread
            // that may not store message bodies at all.
            'consent_logged_at' => now(),
        ];
    }
}
