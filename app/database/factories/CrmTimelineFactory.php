<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CrmTimeline;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<CrmTimeline>
 */
final class CrmTimelineFactory extends Factory
{
    protected $model = CrmTimeline::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'event_type' => fake()->randomElement(['review_left', 'sms_sent', 'call_missed', 'booking_made']),
            'source' => 'system',
            'summary' => fake()->sentence(6),
            'metadata' => [],
            'occurred_at' => now(),
            'created_at' => now(),
        ];
    }
}
