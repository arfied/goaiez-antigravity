<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use Database\Factories\SuppressionListEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One suppressed identifier on one channel for one tenant
 * (DATA-MODEL §5.6). Checked by the send path before anything is queued.
 *
 * ⚠️ APPEND-ONLY, on {@see OptOut}'s precedent and for the same reason it gives:
 * this is the record produced when somebody said STOP, and deleting a row here
 * does not resubscribe anybody — it makes the platform forget it was told to
 * stop. `OptOut` has carried that guard since it was written and this table,
 * which is the *richer* half of the same refusal, carried none.
 *
 * ⛔ AND SINCE 2026-08-22 SOMETHING ELSE DEPENDS ON THE ROW SURVIVING (8046).
 * `ConsentService` used to copy the identifier into `audit_log` metadata, so an
 * investigator could answer *who was suppressed* from the audit row alone. That
 * copy is gone — `audit_log` is kept for ever (8043) and the copy was an end
 * customer's phone number or email in clear — and what replaced it is the entity
 * reference on the audit row, pointing here. **The pointer is only as good as
 * the target**, so the row that a `consent.withdrawn` entry names must outlive
 * everything except the tenant itself, which it does: `suppression_list` and
 * `audit_log` both cascade on `business_id` and on nothing else.
 *
 * ⚠️ `updating` IS GUARDED AS WELL AS `deleting`, AND THE GENERATION IS THE
 * REASON RATHER THAN THE EXCEPTION. `lift_generation` looks like a column that
 * moves, and it never does: it is part of the row's identity — it sits in
 * `suppression_list_business_channel_identifier_gen_unique` and in the
 * `firstOrCreate()` **match** — so a second STOP after a lift is born as a new
 * row rather than an increment. An in-place bump is precisely the edit nobody
 * would notice: it would leave a `suppression_lifts` row matched on the old
 * generation, still clearing a refusal that has moved out from under it.
 *
 * Known gap, the same one {@see AuditLogEntry} documents: a Query Builder mass
 * update or delete bypasses model events entirely.
 *
 * @property-read int $id
 * @property int $business_id
 * @property OutreachChannel $channel
 * @property string $identifier
 * @property string $reason
 * @property SuppressionReason $reason_class
 * @property int $lift_generation
 * @property ?Carbon $created_at
 */
final class SuppressionListEntry extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<SuppressionListEntryFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * The model is one entry; the table is the list.
     */
    protected $table = 'suppression_list';

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'suppression_list is append-only. A refusal is a fact with a date, and '
                .'lift_generation is part of the row identity rather than a counter — a '
                .'second STOP is a new row, released by a suppression_lift.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'suppression_list is append-only. Deleting a row does not resubscribe '
                .'anybody — it makes the platform forget it was told to stop, and it '
                .'orphans the audit_log entry that names it.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OutreachChannel::class,
            'reason_class' => SuppressionReason::class,
            'lift_generation' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
