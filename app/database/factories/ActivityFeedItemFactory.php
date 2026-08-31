<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AutopilotActionType;
use App\Models\ActivityFeedItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a business-level system message — location_id stays null so
 * creating a feed item does not quietly create a location. Pass location_id
 * when the test is about a location's feed.
 *
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<ActivityFeedItem>
 */
final class ActivityFeedItemFactory extends Factory
{
    protected $model = ActivityFeedItem::class;

    public function definition(): array
    {
        return [
            'action_type' => AutopilotActionType::SystemMessage,
            'title' => 'Checked your Google profile — everything looks right.',
            'metadata' => null,
        ];
    }
}
