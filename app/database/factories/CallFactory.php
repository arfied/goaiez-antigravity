<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CallOutcome;
use App\Models\Call;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **THE NUMBERS ARE `555-01xx`, WHICH IS THE RESERVED FICTIONAL RANGE.**
 * `CLAUDE.md`: never real personal data in a fixture — and a voice fixture is
 * the one where a plausible-looking number is a number somebody's phone rings.
 *
 * @extends Factory<Call>
 */
final class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'provider_call_id' => 'call-'.$this->faker->unique()->numerify('########'),
            'from_e164' => '+1555010'.$this->faker->unique()->numerify('####'),
            'to_e164' => '+15550100000',
            'outcome' => CallOutcome::InProgress,
            'provider_state' => 'RINGING',
            'started_at' => now(),
        ];
    }

    public function missed(): self
    {
        return $this->state(fn (): array => [
            'outcome' => CallOutcome::Missed,
            'provider_state' => 'NO_ANSWER',
            'ended_at' => now(),
        ]);
    }

    public function answered(): self
    {
        return $this->state(fn (): array => [
            'outcome' => CallOutcome::Answered,
            'provider_state' => 'FINISHED',
            'answered_at' => now(),
            'ended_at' => now(),
        ]);
    }
}
