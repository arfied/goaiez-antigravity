<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OutreachChannel;
use App\Models\SuppressionListEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<SuppressionListEntry>
 */
final class SuppressionListEntryFactory extends Factory
{
    protected $model = SuppressionListEntry::class;

    public function definition(): array
    {
        return [
            'channel' => OutreachChannel::Sms,
            'identifier' => fake()->unique()->numerify('+1##########'),
            'reason' => 'opt_out',
        ];
    }
}
