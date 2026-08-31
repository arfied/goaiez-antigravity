<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use App\Models\ProofNumber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProofNumber>
 *
 * ⚠️ **A FIXTURE, AND NEVER HOW THE APPLICATION GETS ONE.** Every column here is
 * a count of rows that live somewhere else, so a factory-made row is by
 * definition an authored number — the exact thing `28` §3.3 forbids in
 * production. It exists so a test can assert the *screen* renders a given state
 * without staging a hundred source rows; anything asserting the numbers are
 * *right* must go through `ProofNumbers::recompute()`.
 */
final class ProofNumberFactory extends Factory
{
    protected $model = ProofNumber::class;

    // No `@return array<string, mixed>` docblock: Laravel's stub generates one
    // and Larastan rejects it, because the parent declares the narrower
    // `array<model property of ProofNumber, mixed>`. Every other factory here
    // omits it for the same reason.
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'period' => ProofNumber::ALL,
            'google_reviews' => 0,
            'leads' => 0,
            'recovered' => 0,
            'computed_at' => now(),
        ];
    }
}
