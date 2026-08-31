<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActuationTier;
use App\Enums\SpeedFix;
use App\Enums\SpeedFixStatus;
use App\Models\SpeedChangeSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * ⚠️ **NO DEFAULT `change_set_id`, DELIBERATELY.** Every row must point at a
 * real `site_changes` row — that is the FK and it is rule 32's whole point — and
 * a factory that conjured one would let a test build a speed fix with no
 * snapshot behind it, which is precisely the state the production path cannot
 * reach. Callers pass the change set they opened.
 *
 * @extends Factory<SpeedChangeSet>
 */
final class SpeedChangeSetFactory extends Factory
{
    protected $model = SpeedChangeSet::class;

    public function definition(): array
    {
        return [
            'fix_key' => SpeedFix::FontDisplaySwap,
            'tier' => ActuationTier::T1,
            'status' => SpeedFixStatus::Decided,
            'baseline' => null,
            'result' => null,
            'decided_at' => now(),
            'applied_at' => null,
        ];
    }
}
