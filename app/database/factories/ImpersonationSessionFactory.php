<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImpersonationEnding;
use App\Enums\ImpersonationMode;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\ImpersonationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImpersonationSession>
 */
final class ImpersonationSessionFactory extends Factory
{
    protected $model = ImpersonationSession::class;

    public function definition(): array
    {
        $mode = ImpersonationMode::View;

        return [
            // A support agent, not an owner. Defaulting to the weakest role
            // that can hold this mode keeps a test that forgets to set one
            // honest — the alternative default, super_admin, passes every gate
            // and would hide an authorisation bug in every test using it.
            'agent_id' => User::factory()->state(['role' => UserRole::SupportAgent]),
            'business_id' => Business::factory(),
            'mode' => $mode,
            'reason' => 'Customer reports the review page is blank on their phone.',
            'ticket_ref' => null,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($mode->ttlMinutes()),
            'created_at' => now(),
        ];
    }

    public function act(): self
    {
        return $this->state(fn (): array => [
            'agent_id' => User::factory()->state(['role' => UserRole::SupportLead]),
            'mode' => ImpersonationMode::Act,
            'ticket_ref' => 'SUP-1042',
            'expires_at' => now()->addMinutes(ImpersonationMode::Act->ttlMinutes()),
        ]);
    }

    /**
     * ⚠️ Not `for()`. Eloquent's factory already has one, with a different
     * meaning and a different signature — overriding it would break every
     * `->for($relation)` call and PHPStan says so in three ways at once.
     */
    public function between(User $agent, Business $business): self
    {
        return $this->state(fn (): array => [
            'agent_id' => $agent->id,
            'business_id' => $business->id,
        ]);
    }

    /**
     * Lapsed, but never closed — the state with no marker on the row.
     *
     * This is the shape the build-failing test needs and the one a factory
     * would not produce by accident: `ended_at` is still null, so anything
     * checking only that column reads this session as live.
     */
    public function expired(): self
    {
        return $this->state(fn (): array => [
            'started_at' => now()->subHours(3),
            'expires_at' => now()->subHour(),
        ]);
    }

    public function ended(): self
    {
        return $this->state(fn (): array => [
            'ended_at' => now(),
            'ended_reason' => ImpersonationEnding::Ended,
        ]);
    }
}
