<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GrowthPageStatus;
use App\Enums\GrowthPageType;
use App\Models\GrowthPage;
use App\Models\Location;
use App\Services\Content\GrowthPages;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant
 * (`LocationFactory`'s note).
 *
 * ⚠️ **FOR ISOLATION AND SHAPE TESTS, NOT FOR EXERCISING THE WRITER.**
 * {@see GrowthPages} is the only supported way to
 * create one of these, and a test that reaches for the factory to build a
 * candidate is testing the table rather than the guards in front of it —
 * `SiteChangeFactory` carries the same warning for the same reason.
 *
 * ⛔ **THE DEFAULT CONTENT IS DELIBERATELY LONG ENOUGH TO GATE.** A two-word
 * body would make every gate fixture fail on readability arithmetic that has
 * nothing to do with what the test is about.
 *
 * @extends Factory<GrowthPage>
 */
final class GrowthPageFactory extends Factory
{
    protected $model = GrowthPage::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'type' => GrowthPageType::Service,
            'slug' => 'services/'.fake()->unique()->slug(3),
            'title' => 'Same-day drain cleaning',
            'meta_description' => 'We clear blocked drains the same day, most days by lunchtime.',
            'content' => 'We clear blocked drains the same day. Most jobs are done by lunch. '
                .'Our van carries a camera, so you see the blockage before we quote. '
                .'Kitchen drains block most often in this town. Tree roots cause the rest. '
                .'We charge one flat price for a home visit and tell you before we start.',
            'target_keyword' => 'blocked drain same day',
            'quality_rating' => null,
            'status' => GrowthPageStatus::Draft,
            'hold_until' => null,
        ];
    }

    /**
     * A page the gate turned down: held, with no release time, for a person.
     */
    public function held(): self
    {
        return $this->state(fn (): array => [
            'status' => GrowthPageStatus::Held,
            'hold_until' => null,
        ]);
    }

    /**
     * A page that is already on the tenant's website.
     *
     * ⚠️ **`published_at` MOVES WITH THE STATUS OR THE DATABASE REFUSES THE
     * ROW.** `growth_pages_published_at_matches_status` is a biconditional, so a
     * state that set one without the other would fail inside the factory rather
     * than in the assertion — which is the point of the constraint, and worth
     * knowing before somebody writes `->published()` with an overridden status.
     */
    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => GrowthPageStatus::Published,
            'hold_until' => null,
            'published_at' => now(),
        ]);
    }
}
