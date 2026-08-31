<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OutreachChannel;
use App\Enums\TriageStatus;
use App\Models\Review;
use App\Models\TriageConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open recovery conversation on a low first-party review.
 *
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<TriageConversation>
 */
final class TriageConversationFactory extends Factory
{
    protected $model = TriageConversation::class;

    public function definition(): array
    {
        return [
            'review_id' => Review::factory()->state(['rating' => 2]),
            'channel' => OutreachChannel::Sms,
            'status' => TriageStatus::Open,
            'transcript' => [],
        ];
    }

    public function resolved(): self
    {
        return $this->state(fn (): array => [
            'status' => TriageStatus::Resolved,
            'resolution' => 'Owner called the customer and made it right.',
        ]);
    }
}
