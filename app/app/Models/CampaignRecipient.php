<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CampaignRecipientStatus;
use App\Enums\SendRefusalReason;
use Database\Factories\CampaignRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One contact on one campaign, and what became of them.
 *
 * ⚠️ **THE ROW IS WRITTEN WHEN THE AUDIENCE IS RESOLVED, NOT WHEN THE SEND
 * HAPPENS.** That is what makes "who was this campaign for" and "who did it
 * reach" two different, answerable questions — and it is what makes a campaign
 * stopped mid-flight resumable on the remainder rather than restartable from
 * the top. A run that recomputed its audience each batch would drop a contact
 * who replied halfway through *after* deciding to message them, and its own
 * record of who it was for would disagree with itself between passes.
 *
 * ⚠️ **`send_key` HOLDS NO IDENTIFIER.** `SendKey` hashes the recipient into a
 * sha256 precisely so the value can reach a row, a log line and a Horizon tag
 * without a mobile number reaching any of them.
 *
 * ⛔ **`send_key` AND `send_handle` ARE TWO DIFFERENT THINGS AND THE CONFUSION
 * BETWEEN THEM WAS A REAL DEFECT** (7360). `send_key` is **local**: it is this
 * application's claim on one send, arbitrated by a unique index on
 * `outreach_messages`, and it has never been sent to any vendor. `send_handle`
 * is the id this application put **on the carrier's wire** as
 * `destinations[].messageId`, so it is the only value `GET /sms/3/logs` can be
 * asked about. 7192(d) said the `send_key` was kept *"so that lookup has
 * something to start from"*; it could not have been, and this column is what
 * makes the sentence true.
 *
 * @property CampaignRecipientStatus $status
 * @property ?SendRefusalReason $refusal_reason
 * @property ?string $send_key
 * @property ?string $send_handle
 * @property ?Carbon $carrier_answered_at
 * @property ?string $provider_message_id
 * @property ?string $media_path
 * @property ?int $media_bytes
 * @property ?Carbon $sent_at
 * @property ?Carbon $first_deferred_at
 */
final class CampaignRecipient extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CampaignRecipientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CampaignRecipientStatus::class,
            'refusal_reason' => SendRefusalReason::class,
            // Nullable on purpose — a null is an image whose size nobody
            // recorded, never a zero-byte one (4762).
            'media_bytes' => 'integer',
            'sent_at' => 'datetime',
            // 7501's full stop: the moment the carrier told us something final
            // about this message, which is what retires the question
            // `send_handle` asks. ⚠️ On the two arms that leave the status
            // alone it is written with timestamps DISABLED — `updated_at` is
            // the attempt time for an `unknown` row and `SendCollisionArbiter`
            // measures a live person's marketing block against it.
            'carrier_answered_at' => 'datetime',
            // 2687's clock. Set on the first deferral and never moved after,
            // which is the whole difference between it and `updated_at`.
            'first_deferred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
