<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignRecipientStatus;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * @extends Factory<CampaignRecipient>
 */
final class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    // No `@return array<string, mixed>` docblock — see CreditLedgerEntryFactory.
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'customer_id' => Customer::factory(),
            'status' => CampaignRecipientStatus::Pending,
        ];
    }

    /**
     * Sent — the status, the provider id and the timestamp together, because
     * the CHECK refuses a sent row that names no message or carries no time.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => CampaignRecipientStatus::Sent,
            'provider_message_id' => 'msg-'.fake()->uuid(),
            'sent_at' => now(),
        ]);
    }
}
