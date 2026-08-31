<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DunningOutcome;
use App\Models\DunningAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * Does NOT default `business_id` — `BelongsToTenant` fills it from the tenant in
 * context.
 *
 * ⚠️ **A FACTORY ROW IS NOT A COHERENT SCHEDULE**, the same warning
 * `CreditLedgerEntryFactory` carries. It sets one self-consistent attempt and
 * knows nothing about the ones before it, so two `create()` calls can produce a
 * sequence with two "attempt 1" rows in different sequences and no ordering
 * between them. That is right for testing the model, the unique key and
 * isolation — and wrong for testing the suspension rule, which must be built
 * through `App\Services\Billing\Dunning`, the only thing that knows what number
 * comes next.
 *
 * @extends Factory<DunningAttempt>
 */
final class DunningAttemptFactory extends Factory
{
    protected $model = DunningAttempt::class;

    public function definition(): array
    {
        return [
            'sequence' => 1,
            'attempt' => 1,
            'outcome' => DunningOutcome::Declined,
            'reason_code' => null,
            'attempted_at' => Carbon::now(),
            'next_attempt_at' => null,
        ];
    }
}
