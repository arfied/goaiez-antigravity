<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeedbackPage;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FeedbackPage>
 *
 * NO DEFAULT business_id OR location_id, deliberately, the same reasoning
 * LocationFactory gives for not defaulting business_id to Business::factory():
 * both are NOT NULL foreign keys, and `feedback_pages.tenant_write` requires
 * the row's own `business_id` to match the session tenant on INSERT — a
 * factory-invented parent would either fail RLS outright or silently mint a
 * *second* tenant for a test meaning one. `forLocation()` is the one state
 * this factory ships, because FeedbackPages::provisionFor() is otherwise the
 * only writer and a bare `FeedbackPage::factory()->create()` could not
 * previously insert a row at all — both foreign keys were absent from
 * definition().
 */
final class FeedbackPageFactory extends Factory
{
    protected $model = FeedbackPage::class;

    public function definition(): array
    {
        return [
            'slug' => Str::slug(fake()->company()).'-'.Str::lower(Str::random(4)),
            'is_published' => true,
        ];
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }

    /**
     * The location this page belongs to, and the tenant it belongs to along
     * with it — read from the location rather than from the ambient tenant,
     * for the same reason FeedbackPages::assertBelongsToTenant() does: the two
     * ids are both in hand here, so they cannot disagree with each other.
     */
    public function forLocation(Location $location): self
    {
        return $this->state(fn (): array => [
            'business_id' => $location->business_id,
            'location_id' => $location->id,
        ]);
    }
}
