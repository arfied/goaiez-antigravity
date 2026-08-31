<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\OwnerNotificationKind;
use App\Services\Sms\SentText;
use Database\Factories\OwnerNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * That this platform texted a business's own account holder — wave 40 lane A,
 * decision 10820.
 *
 * ⛔ **THE ROW IS EVIDENCE THAT A CARRIER TOOK THE MESSAGE, AND NOTHING MORE.**
 * See the creating migration: `provider_message_id` is `NOT NULL` and is filled
 * from {@see SentText} after the transport answers, so a row
 * cannot exist for a send that was never made — and equally, a row is **not** a
 * delivery. Nothing in `app/` reads an owner-channel delivery receipt.
 *
 * ⚠️ **APPEND-ONLY, {@see OwnerReply}'s AND {@see OwnerNotificationConsent}'s
 * REASONING APPLIED TO THE OUTBOUND HALF.** What we sent, and when, is a dated
 * fact; a row that could be edited afterwards would let this table disagree
 * with the carrier's own record of the same message.
 *
 * ⛔ **AND UNLIKE {@see OwnerReply}, `deleting` IS PERMITTED — READ WHY BEFORE
 * COPYING EITHER.** That model refuses a delete outright, on the ground that
 * *"the business's own erasure cascades them away in the database, which is the
 * only way one goes"*. That sentence was written when nothing pruned either
 * table; `App\Console\Commands\PruneOwnerChannel` now does, on the
 * `owner_channel.retention_days` period, and a retention sweep is exactly the
 * second honest way a row goes. ⚠️ **A mass `Builder::delete()` would have
 * bypassed a model guard anyway** — `CLAUDE.md`'s `$guarded` finding one axis
 * over: `Eloquent\Builder::delete()` calls `$this->toBase()->delete()` and
 * fires no model event — **so a guard here would have refused the honest
 * caller and waved the mass one through.** The guard that means something is
 * the one on `updating`, which is kept.
 *
 * @property-read int $id
 * @property int $business_id
 * @property OwnerNotificationKind $kind
 * @property string $occasion
 * @property string $provider_message_id
 * @property Carbon $sent_at
 * @property ?Carbon $created_at
 */
final class OwnerNotification extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<OwnerNotificationFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'owner_notifications is append-only. What we sent, and when, is a dated event; '
                .'editing this row would let it disagree with the carrier\'s own record.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OwnerNotificationKind::class,
            'sent_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
