<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OwnerReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A stored account-holder reply — does NOT default business_id;
 * BelongsToTenant fills it from the tenant in context.
 *
 * @extends Factory<OwnerReply>
 */
final class OwnerReplyFactory extends Factory
{
    protected $model = OwnerReply::class;

    public function definition(): array
    {
        return [
            'provider_message_id' => 'mo-'.fake()->unique()->uuid(),
            'body' => 'Yes, go ahead.',
            'received_at' => now(),
            'created_at' => now(),
        ];
    }
}
