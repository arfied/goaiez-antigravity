<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CampaignReplyOrigin;
use App\Models\CampaignReply;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * business_id is deliberately absent — BelongsToTenant fills it from context,
 * and a factory that created its own parent would make a second tenant.
 *
 * ⚠️ **`inbound_message_id` IS DELIBERATELY ABSENT TOO, AND IT IS REQUIRED.**
 * `inbound_messages` is platform-scoped and its rows are written by the carrier
 * webhook; a factory that minted one here would produce a linkage to a message
 * nobody received. Every caller passes the real row.
 *
 * @extends Factory<CampaignReply>
 */
final class CampaignReplyFactory extends Factory
{
    protected $model = CampaignReply::class;

    // No `@return array<string, mixed>` docblock — see CreditLedgerEntryFactory.
    public function definition(): array
    {
        return [
            'origin' => CampaignReplyOrigin::Reactivation,
            'campaign_id' => 1,
            'recipient_id' => 1,
            'customer_id' => Customer::factory(),
            'occasion' => 'campaign:1:step:1',
            'sent_at' => now()->subHour(),
            'created_at' => now(),
        ];
    }
}
