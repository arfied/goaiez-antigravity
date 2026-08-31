<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory). Titles use outcome language and carry no
 * personal data, matching the toast rules (decision 104).
 *
 * @extends Factory<Notification>
 */
final class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'weekly_report',
            'channel' => 'email',
            'title' => 'Your weekly results are ready',
            'body' => 'Three new reviews and two recovered customers this week.',
        ];
    }
}
