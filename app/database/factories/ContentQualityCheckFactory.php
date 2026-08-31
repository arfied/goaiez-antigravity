<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentQualityFailure;
use App\Enums\DemandSource;
use App\Models\ContentQualityCheck;
use App\Models\GrowthPage;
use App\Services\Content\ContentQuality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A passing check. Does NOT default business_id — BelongsToTenant fills it from
 * the tenant in context (see LocationFactory).
 *
 * ⛔ **`page_id` IS A REAL `growth_pages` ROW SINCE 2026-08-19.** It used to be
 * `fake()->numberBetween(1, 1_000_000)`, because the table it points at did not
 * exist; it does now, and the foreign key the Stage 0 migration promised would
 * *"land with that table"* landed with it.
 *
 * ⚠️ **FOR ISOLATION AND SHAPE TESTS, NOT FOR EXERCISING THE GATE.**
 * {@see ContentQuality} is the only supported way
 * to create one of these, and a test that reaches for the factory to build a
 * verdict is testing the table rather than the gate in front of it.
 *
 * ⚠️ **EVERY STATE HERE SATISFIES THE THREE-WAY CHECK CONSTRAINT**, and there is
 * one per state on purpose: `passed` true with no reasons, false with reasons,
 * null with an unavailable reason. A fixture that could not express the third
 * state would leave decision 347's whole distinction untestable from the table
 * side.
 *
 * @extends Factory<ContentQualityCheck>
 */
final class ContentQualityCheckFactory extends Factory
{
    protected $model = ContentQualityCheck::class;

    public function definition(): array
    {
        return [
            'page_id' => GrowthPage::factory(),
            'uniqueness_score' => fake()->numberBetween(80, 100),
            'first_party_data_count' => fake()->numberBetween(3, 12),
            'demand_evidence' => [[
                'source' => DemandSource::SearchConsoleQuery->value,
                'query' => 'blocked drain same day',
                'count' => fake()->numberBetween(20, 400),
            ]],
            // A grade, so lower is better — see App\Support\Readability.
            'readability_score' => fake()->numberBetween(5, 8),
            'passed' => true,
            'failure_reasons' => null,
            'unavailable_reason' => null,
            'checked_at' => now(),
        ];
    }

    public function failing(): self
    {
        return $this->state(fn (): array => [
            'uniqueness_score' => fake()->numberBetween(0, 40),
            'passed' => false,
            'failure_reasons' => [ContentQualityFailure::UniquenessBelowThreshold->value],
            'unavailable_reason' => null,
        ]);
    }

    /**
     * No verdict exists — decision 347's third state.
     */
    public function undetermined(): self
    {
        return $this->state(fn (): array => [
            'passed' => null,
            'failure_reasons' => null,
            'unavailable_reason' => 'moderation:refused',
        ]);
    }
}
