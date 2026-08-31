<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AutopilotSettings;
use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Everything except the location comes from the column defaults, which ARE the
 * product's defaults (ungated, `29` §2 rule 37) — restating them here would be
 * a second copy that drifts.
 *
 * Does NOT default business_id — BelongsToTenant fills it from the tenant in
 * context (see LocationFactory).
 *
 * ⚠️ **THERE WAS A `gatingAcknowledged()` STATE AND IT IS GONE** (2074, 2660).
 * It set `gating_ack_at`, which was the difference between a location whose
 * invite thresholds applied and one whose did not, and thirteen tests reached
 * for it to mean "thresholds are live here". Thresholds are now live
 * everywhere, so the state has nothing to express — a no-op state left in place
 * would have gone on reading, at every one of those call sites, as though the
 * setup were doing something.
 *
 * @extends Factory<AutopilotSettings>
 */
final class AutopilotSettingsFactory extends Factory
{
    protected $model = AutopilotSettings::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
        ];
    }
}
