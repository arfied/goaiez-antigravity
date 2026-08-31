<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\SupportChannel;
use App\Enums\SupportMessageAuthor;
use App\Services\Support\SupportDesk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing somebody said in a support thread (T137 `SL-7`).
 *
 * Tenant-owned for {@see SupportTicket}'s reasons, and one of its own: this is
 * where the free text lives — what a tenant typed, and what we wrote back in the
 * platform's name. It carries `business_id` in its own right rather than
 * reaching the tenant through the ticket, because a join does not inherit the
 * parent's scope and an RLS policy cannot read one (`multi-tenancy` skill).
 *
 * ⚠️ **NO `updated_at`, AND NOTHING IN `app/` EDITS A ROW.** A thread is a
 * record of what was said; editing one afterwards would make the tenant's copy
 * and ours disagree about a conversation both sides remember. That is a
 * convention held by {@see SupportDesk} being the only
 * writer, not by a database trigger — stated plainly rather than claimed as an
 * enforcement layer (314–316).
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $support_ticket_id
 * @property SupportMessageAuthor $author
 * @property ?int $author_user_id
 * @property SupportChannel $channel
 * @property string $body
 * @property ?string $external_ref
 * @property CarbonImmutable $created_at
 */
final class SupportMessage extends Model implements TenantScoped
{
    use BelongsToTenant;

    public const null UPDATED_AT = null;

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
            'support_ticket_id' => 'integer',
            'author' => SupportMessageAuthor::class,
            'author_user_id' => 'integer',
            'channel' => SupportChannel::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
