<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OutreachChannel;
use App\Models\OwnerNotificationConsent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The owner channel's own consent evidence (10540) — `ConsentRecordFactory`'s
 * shape, with no `customer_id` to fill.
 *
 * proof carries ip_hash, never an IP — a fixture rule as much as a production
 * one. Does NOT default business_id — BelongsToTenant fills it from the
 * tenant in context.
 *
 * @extends Factory<OwnerNotificationConsent>
 */
final class OwnerNotificationConsentFactory extends Factory
{
    protected $model = OwnerNotificationConsent::class;

    public function definition(): array
    {
        return [
            'channel' => OutreachChannel::Sms,
            'method' => 'checkbox',
            'e164' => '+15555550100',
            'accepted_by' => 'user:1',
            'disclosure_version' => 'owner-notify-2026-08-27.1',
            'proof' => [
                'ip_hash' => fake()->sha256(),
                'url' => 'https://example.test/setup/done',
                'user_agent' => 'Mozilla/5.0 (test)',
                'checkbox_state' => 'checked_by_user',
                'disclosure_text' => 'We will text this number about your account.',
            ],
            'created_at' => now(),
        ];
    }
}
