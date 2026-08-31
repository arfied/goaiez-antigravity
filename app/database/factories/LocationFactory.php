<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
final class LocationFactory extends Factory
{
    protected $model = Location::class;

    /**
     * Deliberately does NOT default business_id to Business::factory().
     *
     * A factory that creates its own parent creates a *second* tenant, so a test
     * meaning "two locations in one business" quietly gets two businesses and
     * proves nothing about isolation. BelongsToTenant fills business_id from the
     * tenant in context instead, which is also what production does — and a test
     * with no tenant established fails loudly rather than inventing one.
     *
     * Pass it explicitly with forBusiness() when a test needs a specific parent.
     *
     * ⛔ **NO `website_url`, AND ITS REMOVAL IS PART OF THE SLICE THAT GAVE THE
     * COLUMN A WRITER** (5540). This factory was the column's *only* writer in
     * the whole repository — `pixel_tenant_id`'s shape (4961) — so every test of
     * anything reading it passed against an address no real tenant has, while a
     * production location's was null for every tenant. Row 9 slice B's own gate
     * is that the tier scanner reads "we do not know yet" for a location with no
     * confirmed website, and a factory default would have made that arm
     * unreachable in the suite that is supposed to prove it.
     *
     * ⛔ **AND `address` AND `primary_phone` WENT WITH IT ON 2026-08-20, THREE
     * LINES BELOW THE PARAGRAPH EXPLAINING WHY** (6100). This factory was the
     * only writer of those two in the whole repository as well — the same defect,
     * in the same file, under a docblock arguing at length against it, which is
     * 314–316's shape at its most literal. **Two shipped readers depended on
     * them**: the carrier-mandated HELP reply, which answered every member of the
     * public with `support@goaiez.com` because `contactFor()` was permanently
     * null, and the assistant's directions skill, which was dark for every real
     * tenant. Every test lit both from here, so both suites were green
     * throughout.
     *
     * ⚠️ **AND THE CHECKS ARE WHAT MAKE THE REMOVAL STICK RATHER THAN THIS
     * PARAGRAPH.** `locations_phone_and_confirmation_travel_together` and its
     * address twin make a value with no confirmation unrepresentable, so putting
     * either back here fails at the database rather than passing quietly. A test
     * that needs a stated phone or address calls
     * `App\Services\Tenant\LocationDetails::state()`, which is also how it
     * proves the writer rather than the fixture.
     *
     * ⚠️ **AND THERE IS DELIBERATELY NO `withWebsite()` STATE TO REPLACE IT.**
     * The column and its confirmation are bound together by a CHECK, and a state
     * writing both would be a second confirmer — the exact thing 220's literal
     * `true` parameter exists to make impossible. A test that needs a confirmed
     * website calls `LocationWebsite::confirm()`, which is also how it proves the
     * writer rather than the fixture.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'timezone' => 'America/New_York',
            'google_location_id' => fake()->unique()->numerify('locations/############'),
            'google_place_id' => 'ChIJ'.fake()->regexify('[A-Za-z0-9_-]{23}'),
            'google_cid' => (string) fake()->randomNumber(9, true),
            'google_maps_url' => 'https://maps.app.goo.gl/'.fake()->regexify('[A-Za-z0-9]{11}'),
            'current_rating' => fake()->randomFloat(1, 3.0, 5.0),
            'review_count' => fake()->numberBetween(0, 500),
            'is_autopilot_active' => true,
        ];
    }

    public function forBusiness(int $businessId): self
    {
        return $this->state(fn (): array => ['business_id' => $businessId]);
    }
}
