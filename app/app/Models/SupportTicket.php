<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\SupportChannel;
use App\Enums\SupportTicketStatus;
use App\Services\Support\SupportDesk;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One request a tenant raised with us (T137 `SL-7`).
 *
 * Tenant-owned, scoped and RLS-`FORCE`d: the thread is the tenant's own record
 * of what they asked and what we answered, and they are its primary reader.
 * Staff read it inside {@see Tenancy::actingAs()}, one account at a
 * time, through {@see SupportDesk} — which a chokepoint
 * lint holds as the only file in `app/` that may name this model.
 *
 * ⚠️ **NOT `Conversation`.** That model is DATA-MODEL §5.9's — a tenant's thread
 * with *their* customer, keyed on `customer_id`, gated on `consent_logged_at`
 * because CIPA requires notice before chat capture. Nothing on this table is a
 * consumer's speech, so none of that applies; conflating the two would put a
 * vendor support request behind a consent gate that has no subject.
 *
 * @property-read int $id
 * @property int $business_id
 * @property ?int $opened_by_user_id
 * @property SupportChannel $channel
 * @property string $subject
 * @property SupportTicketStatus $status
 * @property CarbonImmutable $last_message_at
 * @property ?CarbonImmutable $resolved_at
 * @property CarbonImmutable $created_at
 */
final class SupportTicket extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return HasMany<SupportMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_by_user_id' => 'integer',
            'channel' => SupportChannel::class,
            'status' => SupportTicketStatus::class,
            'last_message_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
