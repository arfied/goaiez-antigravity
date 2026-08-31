<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SiteChangeActor;
use App\Models\Location;
use App\Models\SiteChangeQuarantine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **FOR ISOLATION AND SHAPE TESTS, NOT FOR EXERCISING THE WRITER.**
 * `SiteChangeQuarantines` is the only supported way to open or release one of
 * these, and a test reaching for this factory to prove the gate is testing the
 * table rather than the rule in front of it — `SiteChangeFactory`'s own note.
 *
 * @extends Factory<SiteChangeQuarantine>
 */
final class SiteChangeQuarantineFactory extends Factory
{
    protected $model = SiteChangeQuarantine::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'change_type' => 'growth_page',
            'site_change_id' => null,
            'quarantined_at' => now(),
            'quarantined_by' => SiteChangeActor::Autopilot,
            'quarantined_reason' => 'measured regression',
            'released_at' => null,
            'released_by' => null,
            'released_reason' => null,
        ];
    }
}
