<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OwnerNotificationKind;
use App\Models\OwnerNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A record that this platform texted an account holder — does NOT default
 * business_id; BelongsToTenant fills it from the tenant in context.
 *
 * @extends Factory<OwnerNotification>
 */
final class OwnerNotificationFactory extends Factory
{
    protected $model = OwnerNotification::class;

    public function definition(): array
    {
        return [
            'kind' => OwnerNotificationKind::UrgentEscalation,
            'occasion' => 'mo-'.fake()->unique()->uuid(),
            'provider_message_id' => 'mt-'.fake()->unique()->uuid(),
            'sent_at' => now(),
            'created_at' => now(),
        ];
    }
}
