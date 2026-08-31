<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Models\Location;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a first-party review — google_review_id null, per DATA-MODEL
 * §5.14 — with the location created inside the established tenant.
 *
 * Does NOT default business_id: BelongsToTenant fills it from the tenant in
 * context (see LocationFactory). The nested Location factory inherits that
 * same tenant, so nothing here can cross the boundary.
 *
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'source' => ReviewSource::FirstParty,
            'google_review_id' => null,
            'is_platform' => true,
            'ingest_method' => 'api',
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->paragraph(),
            'reviewer_name' => fake()->name(),
            'review_create_time' => now()->subDay(),
            'status' => ReviewStatus::Pending,
        ];
    }

    /**
     * An ingested Google review. Never held, hidden, approved, or moderated —
     * tests asserting gating behaviour must not use this state.
     */
    public function fromGoogle(): self
    {
        return $this->state(fn (): array => [
            'source' => ReviewSource::Google,
            'google_review_id' => fake()->unique()->regexify('[A-Za-z0-9_-]{27}'),
            'is_platform' => false,
            'ingest_method' => 'api',
        ]);
    }

    public function approved(): self
    {
        return $this->state(fn (): array => [
            'status' => ReviewStatus::Approved,
            'approved_at' => now(),
            'approved_by' => 'autopilot',
        ]);
    }
}
