<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignAudience;
use App\Enums\CampaignKind;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **THE DEFAULT IS `Draft` AND UNCONFIRMED, WHICH IS THE UNHELPFUL ONE ON
 * PURPOSE.** A factory that produced a sendable campaign would let a test send
 * to somebody without ever going through `Campaigns::confirm()` — and CONFIRM's
 * whole subject is the first send of a new campaign type (decision 2106). A test
 * that wants a sendable one says `->confirmed()`, in one line.
 *
 * @extends Factory<Campaign>
 */
final class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    // No `@return array<string, mixed>` docblock: Laravel's stub generates one
    // and Larastan rejects it, because the parent declares the narrower
    // `array<model property of Campaign, mixed>`. Every other factory here omits
    // it for the same reason.
    public function definition(): array
    {
        return [
            'name' => 'Winter reactivation',
            'audience' => CampaignAudience::Dormant,
            'status' => CampaignStatus::Draft,
            'body_template' => 'Hi {name}, it has been a while.',
            'created_by' => 'user:1',
        ];
    }

    /**
     * Confirmed and scheduled — the pair together, because the CHECK refuses a
     * sendable status without a confirmation, and refuses half a confirmation.
     */
    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => CampaignStatus::Scheduled,
            'confirmed_at' => now(),
            'confirmation_actor' => 'user:1',
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['audience' => CampaignAudience::ManualSelect]);
    }

    /**
     * An SMS broadcast rather than a reactivation — decision 3310.
     *
     * ⚠️ **THE STATE IS ONE COLUMN AND UNLOCKS NOTHING.** It does not give the
     * tenant a 10DLC filing, a number of their own or a purchased balance; a
     * factory that arranged all three would let a test send a broadcast without
     * ever passing the guard those three exist for, which is `confirmed()`'s
     * argument one method up.
     */
    public function broadcast(): static
    {
        return $this->state(fn (): array => ['kind' => CampaignKind::Broadcast]);
    }
}
