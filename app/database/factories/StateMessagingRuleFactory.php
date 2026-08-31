<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\StateMessagingRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StateMessagingRule>
 */
final class StateMessagingRuleFactory extends Factory
{
    protected $model = StateMessagingRule::class;

    public function definition(): array
    {
        return [
            'state' => 'FL',
            // The prohibited window, wrapping midnight — which is the shape
            // every real statute takes. A factory default that did not wrap
            // would let `prohibitsAt()`'s wrapping branch go untested by
            // accident.
            'quiet_hours_start' => '20:00:00',
            'quiet_hours_end' => '08:00:00',
            'requires_written_consent' => false,
            'citation' => 'Fla. Stat. § 501.059',
            'effective_from' => '2021-07-01',
            'notes' => null,
        ];
    }

    public function requiringWrittenConsent(): self
    {
        return $this->state(fn (): array => ['requires_written_consent' => true]);
    }

    public function forState(string $state): self
    {
        return $this->state(fn (): array => ['state' => strtoupper($state)]);
    }
}
