<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MessagingLane;
use App\Enums\OutreachChannel;
use App\Enums\OutreachStatus;
use App\Models\Customer;
use App\Models\OutreachMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A queued Lane A review request. Does NOT default business_id —
 * BelongsToTenant fills it from the tenant in context (see LocationFactory);
 * the nested Customer factory inherits the same tenant.
 *
 * @extends Factory<OutreachMessage>
 */
final class OutreachMessageFactory extends Factory
{
    protected $model = OutreachMessage::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'channel' => OutreachChannel::Sms,
            'purpose' => 'review_request',
            'lane' => MessagingLane::Platform,
            // ⛔ **THIS STRING'S LENGTH IS NOW PART OF THE PRICE — 2026-08-30
            // (12461).** A send costs `ceil(characters / 160) + photos`, and at
            // 66 characters this body is exactly one credit. **Lengthening it
            // past 160 silently doubles the charge in every credit fixture in
            // the suite**, and the failures would appear as off-by-one balance
            // assertions in files that have nothing to do with this one.
            'body' => 'Thanks for choosing us! Would you share how it went? (via GO AI EZ)',
            // ⚠️ **STATED RATHER THAN LEFT NULL, AND IT IS NOT A GUESS.** This
            // definition builds a plain Lane A review-request text — there is no
            // media on it — so `0` is what the row actually is. Null on this
            // column means *sent before the column existed*, which is a thing a
            // factory can never truthfully build.
            'media_count' => 0,
            'status' => OutreachStatus::Queued,
        ];
    }

    /**
     * The same send with pictures on it — one credit each, decision 12461.
     *
     * A state rather than a second default, because `media_count` is half of
     * what `App\Services\Billing\SendCredits` reads to price a send: a factory
     * that varied it would make a balance assertion depend on which fixture
     * happened to build the row.
     *
     * ⚠️ **THE PARAMETER EXISTS BECAUSE THE PRICE IS NO LONGER A BOOLEAN.** This
     * was `['carried_media' => true]` until 2026-08-30, when the owner ruled
     * that each photo counts; a state that could only say *"some"* cannot set up
     * the case the ruling actually changed.
     */
    public function carryingMedia(int $photos = 1): self
    {
        return $this->state(fn (): array => ['media_count' => $photos]);
    }

    public function sent(): self
    {
        return $this->state(fn (): array => [
            'status' => OutreachStatus::Sent,
            'sent_at' => now(),
            'provider_msg_id' => fake()->unique()->uuid(),
        ]);
    }
}
