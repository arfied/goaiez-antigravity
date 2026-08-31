<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CampaignReplyOrigin;
use App\Services\Campaigns\CampaignContext;
use Database\Factories\CampaignReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The record that an inbound reply answers one of this tenant's sends — R20.
 *
 * ⚠️ **IT IS THE STORED FORM OF {@see CampaignContext} AND NOT A SECOND
 * VOCABULARY.** {@see self::toContext()} is the only way a row becomes one, so
 * the DTO's rules — ids and an occasion, never a number and never a body — hold
 * here because there is nothing else on the row to put in it.
 *
 * ⚠️ **`created_at` ONLY.** The linkage is a fact about a message that already
 * arrived, and it is never revised: revising it would rewrite which campaign a
 * customer's words were filed against. `UPDATED_AT` is null for the reason
 * `outreach_messages` sets it — a column nothing may move should not exist.
 *
 * ⚠️ **`conversation_id` IS NOT ON {@see CampaignContext} AND MUST NOT GO
 * THERE** (4236). It is the key this row is *found by*, not part of the answer:
 * the DTO travels into job payloads, Horizon tags and the model prompt, and an
 * internal thread handle in a prompt is one more thing a customer can ask the
 * assistant to read back. The reader hands out the context; the column stays
 * here.
 *
 * @property CampaignReplyOrigin $origin
 * @property ?int $campaign_id
 * @property int $recipient_id
 * @property ?int $customer_id
 * @property ?int $conversation_id
 * @property int $inbound_message_id
 * @property string $occasion
 * @property Carbon $sent_at
 */
final class CampaignReply extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CampaignReplyFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * The context this row is the stored form of.
     *
     * ⚠️ **THE ONE PLACE A ROW BECOMES A DTO.** A second construction site is
     * how the two drift, and what drifts here travels into the model prompt —
     * see {@see CampaignContext}'s own docblock for what that costs.
     */
    public function toContext(): CampaignContext
    {
        return new CampaignContext(
            origin: $this->origin,
            campaignId: $this->campaign_id,
            recipientId: $this->recipient_id,
            customerId: $this->customer_id,
            sentAt: $this->sent_at,
            occasion: $this->occasion,
        );
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => CampaignReplyOrigin::class,
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
