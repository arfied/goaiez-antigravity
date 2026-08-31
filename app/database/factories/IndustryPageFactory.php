<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IndustryFamily;
use App\Models\IndustryPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndustryPage>
 */
final class IndustryPageFactory extends Factory
{
    protected $model = IndustryPage::class;

    /**
     * ⚠️ `index_mode` FALSE, LIKE THE COLUMN AND LIKE THE SEED. A factory that
     * defaulted to indexable would make every sitemap test pass by accident and
     * every `noindex` test set the state it was asserting about — the two
     * surfaces this column exists to keep in step would then be exercised only
     * in the state nobody ships.
     *
     * ⚠️ THE UNIQUE COLUMNS ARE SEQUENCED RATHER THAN RANDOM. `slug`,
     * `demo_keyword` and `position` all carry unique indexes, and a factory that
     * drew them from a random word list fails on a collision roughly one run in
     * a hundred — which reads as a flaky suite rather than as a factory.
     */
    public function definition(): array
    {
        $n = $this->faker->unique()->numberBetween(1, 30000);

        return [
            'slug' => "industry-{$n}",
            'family' => IndustryFamily::Trades,
            'h1' => "Answered in seconds, {$n}",
            'title' => "Industry {$n} AI Answering | GOAIEZ",
            'meta_desc' => "Every call caught by text in 60 seconds, quotes from your own list, and reviews on autopilot. Sample row {$n}.",
            'hook' => 'The phone rings while both hands are busy.',
            'beat' => 'missed → text-back → booked.',
            'trio' => ['instant text-back', 'quotes from your list', 'reviews on autopilot'],
            'trust' => 'the safety line sends before anything else.',
            'demo_keyword' => "SAMPLE{$n}",
            'faq_picks' => [1, 3, 14],
            'position' => $n,
            'index_mode' => false,
            'old_slugs' => [],
        ];
    }

    public function family(IndustryFamily $family): self
    {
        return $this->state(fn (): array => ['family' => $family]);
    }

    /**
     * The state the owner's flip produces — CC-3 §4.
     */
    public function indexable(): self
    {
        return $this->state(fn (): array => ['index_mode' => true]);
    }
}
