<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NumberState;
use App\Services\Sms\NumberLifecycle;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One lifecycle transition of one `phone_numbers` row — doc 51 §2.4, §10.
 *
 * NOT TENANT-OWNED, AND NO RLS — see the creating migration; `InboundMessage`'s
 * shape, not `phone_numbers`'.
 *
 * ⚠️ **APPEND-ONLY, `InboundMessage`'s `booted()` shape.** A history row that
 * can be edited after the fact is not history: rewriting `to_state` changes
 * which state we claim the number reached, and deleting a quarantine row
 * erases the evidence that it happened.
 *
 * ⚠️ **IT IS THE DURABLE RECORD, NOT A CONVENIENCE COPY OF ONE.** `audit_log` is
 * tenant-owned and RLS-`FORCE`d, so `AuditService::record()` opens with
 * `Tenancy::idOrFail()` — and the shared Lane A number belongs to no tenant, so
 * a transition on it has no audit entry that could be written at all. This table
 * is what always gets the row; the audit entry is written *in addition*, and
 * only when the number names the tenant the transition happened inside. See
 * {@see NumberLifecycle}.
 *
 * @property-read int $id
 * @property int $number_id
 * @property ?NumberState $from_state
 * @property NumberState $to_state
 * @property string $actor
 * @property ?string $reason
 */
final class NumberStateChange extends Model
{
    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'number_state_changes is append-only. A row records that a number moved from '
                .'one state to another; rewriting it changes the lifecycle history the number '
                .'actually had.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'number_state_changes is append-only. Deleting a row erases the evidence that a '
                .'quarantine, a recovery, or any other transition happened.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_state' => NumberState::class,
            'to_state' => NumberState::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
