<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewDestination;
use App\Models\DestinationClick;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id: BelongsToTenant fills it from the tenant in
 * context, and the nested Review factory inherits the same tenant, so nothing
 * here can cross the boundary.
 *
 * @extends Factory<DestinationClick>
 */
final class DestinationClickFactory extends Factory
{
    protected $model = DestinationClick::class;

    public function definition(): array
    {
        return [
            'review_id' => Review::factory(),
            'destination' => ReviewDestination::Google,
            'clicked_at' => now(),
        ];
    }
}
