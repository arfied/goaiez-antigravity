<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use App\Models\ReviewRequestCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * @extends Factory<ReviewRequestCampaign>
 */
final class ReviewRequestCampaignFactory extends Factory
{
    protected $model = ReviewRequestCampaign::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'name' => 'Post-visit review requests',
            'status' => 'active',
        ];
    }
}
