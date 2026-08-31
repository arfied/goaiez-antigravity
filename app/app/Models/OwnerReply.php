<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use Database\Factories\OwnerReplyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * What the account holder said back to a text we sent them — wave 39 lane C.
 *
 * ⛔ **A TENANT'S OWN RECORD OF ITS OWN OWNER, NEVER A CUSTOMER'S.** See the
 * creating migration for why the refusal `App\Models\InboundMessage` states in
 * writing does not reach this table: an inbound STOP has no tenant, and this
 * row is written only once `App\Services\Consent\OwnerConsentService::
 * businessesFor()` has already resolved the reply to exactly one business.
 *
 * ⚠️ **APPEND-ONLY, `OwnerNotificationConsent`'s REASONING APPLIED TO A SECOND
 * KIND OF EVENT.** What somebody said is a dated fact; a row that could be
 * edited afterwards would let this table disagree with the carrier's own
 * record of the same message.
 *
 * ⛔ **THE `deleting` GUARD'S OWN SENTENCE IS NARROWED — WAVE 40 LANE A
 * (10831).** It said the business's own erasure cascading them away *"is the
 * only way one goes"*, and that was true when nothing pruned this table.
 * `App\Console\Commands\PruneOwnerChannel` now does, on
 * `owner_channel.retention_days`, and a retention sweep is the second honest
 * way a row goes. ⚠️ **The guard is KEPT and the pruner reaches past it on
 * purpose**: `Eloquent\Builder::delete()` is
 * `$this->toBase()->delete()` and fires no model event (`CLAUDE.md`'s
 * `$guarded` finding, one axis over), so a mass delete was never the caller
 * this guard could refuse — what it refuses is somebody deleting one row by
 * hand, which is still the thing worth refusing.
 *
 * ⚠️ **`in_reply_to_notification_id` IS AN INFERENCE AND NOT A FACT ABOUT WHAT
 * THE OWNER MEANT** — see the migration that adds it. Null is common and means
 * *"we do not know what this answers"*, never *"this is not a reply"*.
 *
 * @property-read int $id
 * @property int $business_id
 * @property string $provider_message_id
 * @property string $body
 * @property ?int $in_reply_to_notification_id
 * @property ?Carbon $received_at
 * @property ?Carbon $created_at
 */
final class OwnerReply extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<OwnerReplyFactory> */
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
                'owner_replies is append-only. What the account holder said is a dated event; '
                .'editing this row would let it disagree with the carrier\'s own record.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'owner_replies is append-only. Rows are never deleted by hand; the business\'s '
                .'own erasure cascades them away, and owner-channel:prune sweeps them past the '
                .'retention period an operator has stated. Those are the two ways one goes.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
