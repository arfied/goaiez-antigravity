<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReplyStatus;
use App\Models\Reply;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory). The nested Review factory inherits the same
 * tenant.
 *
 * @extends Factory<Reply>
 */
final class ReplyFactory extends Factory
{
    protected $model = Reply::class;

    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'text' => fake()->paragraph(),
            'status' => ReplyStatus::Draft,
        ];
    }

    public function posted(): self
    {
        return $this->state(fn (): array => [
            'status' => ReplyStatus::Posted,
            'posted_at' => now(),
            'posted_by' => 'autopilot',
        ]);
    }
}
