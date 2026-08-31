<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActuationTier;
use App\Enums\SiteChangeActor;
use App\Enums\SiteChangeVerdict;
use App\Models\Location;
use App\Models\SiteChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **THIS FACTORY IS FOR ISOLATION AND SHAPE TESTS, NOT FOR EXERCISING THE
 * WRITER.** `SiteChanges` is the only supported way to create one of these, and
 * a test that reaches for the factory to build a change set is testing the table
 * rather than the rule 32 guard in front of it. `applied_at` is null here for
 * the same reason the column exists: opened is not applied.
 *
 * @extends Factory<SiteChange>
 */
final class SiteChangeFactory extends Factory
{
    protected $model = SiteChange::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'url' => 'https://example.test/services/drain-cleaning',
            'change_type' => 'meta_description',
            'tier' => ActuationTier::T1,
            'before_snapshot' => ['meta_description' => 'Drain cleaning.'],
            'after_snapshot' => ['meta_description' => 'Same-day drain cleaning in Nashville.'],
            // ⚠️ **SET RATHER THAN LEFT TO THE COLUMN DEFAULT.** The default
            // `{}` applies at the database; a model this factory has just
            // created has never read the row back, so the attribute would be
            // null in memory and the change set built from it would refuse a
            // typed array (5772).
            'withheld_fields' => [],
            'applied_by' => SiteChangeActor::Autopilot,
            'applied_at' => null,
            'verdict' => SiteChangeVerdict::Pending,
        ];
    }

    /**
     * A change the adapter reported writing.
     */
    public function applied(): self
    {
        return $this->state(fn (): array => ['applied_at' => now()]);
    }
}
