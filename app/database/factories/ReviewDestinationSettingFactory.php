<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewDestination;
use App\Models\Location;
use App\Models\ReviewDestinationSetting;
use App\Services\Destinations\DestinationSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a disabled Google row carrying its default threshold — the state
 * provisioning seeds, so a test that does nothing else starts where a real
 * tenant starts.
 *
 * Does NOT default business_id: BelongsToTenant fills it from the tenant in
 * context (see LocationFactory), so a test with no tenant established fails
 * loudly rather than inventing one.
 *
 * `destination` and `invite_threshold` are guarded on the model, so this factory
 * reaches for the unguarded path deliberately — a factory is allowed to
 * construct states the service would refuse, which is exactly what the CHECK
 * constraint tests need.
 *
 * @extends Factory<ReviewDestinationSetting>
 */
final class ReviewDestinationSettingFactory extends Factory
{
    protected $model = ReviewDestinationSetting::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'destination' => ReviewDestination::Google,
            'enabled' => false,
            'invite_threshold' => $this->defaultThreshold(ReviewDestination::Google),
            'link_url' => null,
        ];
    }

    /**
     * A row for a specific destination, carrying that destination's own default
     * threshold — so `ofDestination(Trustpilot)` cannot accidentally build a row
     * the CHECK constraint would refuse.
     *
     * Named ofDestination() rather than for(): Factory::for() is Eloquent's own
     * relationship helper (untyped $factory, optional $relationship), and a
     * narrower-typed override is an incompatible declaration — PHP fatals at
     * class-load, before any test runs.
     */
    public function ofDestination(ReviewDestination $destination): self
    {
        return $this->state(fn (): array => [
            'destination' => $destination,
            'invite_threshold' => $this->defaultThreshold($destination),
        ]);
    }

    /**
     * The same default a real seeded row gets.
     *
     * Resolved through the service rather than read from the enum, because the
     * enum no longer carries our own number — it lives in the registry, so that
     * moving it is a settings row rather than a deploy (1142). A factory
     * hardcoding 4 here would be a second source of truth that stays green
     * while every real tenant seeds something else, which is the shape decision
     * 256 keeps recording.
     */
    private function defaultThreshold(ReviewDestination $destination): int
    {
        return app(DestinationSettings::class)->defaultThresholdFor($destination);
    }
}
