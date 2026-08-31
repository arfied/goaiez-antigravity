<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\MessageCostKind;
use Database\Factories\MessageCostEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One line of the **internal** cost ledger — what a message actually cost us
 * (T137 R9, decision 2134).
 *
 * ⛔ **THIS IS NOT WHAT THE TENANT IS CHARGED AND MUST NEVER BE RENDERED AS IF
 * IT WERE.** Retail lives in `credit_ledger` and its arithmetic is
 * `App\Services\Billing\SmsCreditUnits`' — ⛔ **deliberately not restated here,
 * having been stale twice in a fortnight** (R9 until 9182, 9182 until 12461). This is provider
 * cost in **millicents** — thousandths of a cent, 3729 — for margin visibility,
 * and R9 says plainly: *"never shown as retail, never hardcoded"*. ⚠️ **The unit
 * is not cents and the column name is the only warning a reader gets**: a
 * `cost_millicents` printed as though it were cents overstates by a thousand.
 * An `ArchitectureTest` lint makes
 * `App\Services\Billing\MessageCostLedger` the only file in `app/` allowed to
 * touch this model, which is what turns "never shown" from an instruction into a
 * property: a resource, a Livewire component or an export cannot reach it.
 *
 * ⚠️ **AND THAT SENTENCE WAS STRONGER THAN THE MECHANISM UNTIL 3892** — 314-316's
 * shape, in the docblock whose job is to stop the next reviewer looking. The lint
 * matched `MessageCostEntry::` and `new MessageCostEntry`, and it has to keep
 * `MessageCostEntry::class` legal because that is the type-hint spelling — which
 * left three unblocked routes to `cost_millicents`: `DB::table(…)`, which names no
 * class at all; `app(MessageCostEntry::class)->newQuery()`; and a
 * `hasMany(MessageCostEntry::class)` on `Business`, after which any component has
 * `$business->costEntries->sum('cost_millicents')`. **None was occupied** — this
 * branch's "blast radius is four files" was true on the day it was checked — and a
 * claim that holds by luck is exactly the one nobody re-checks. All four spellings
 * are refused now, each planted and driven red before it counted, and the facade
 * form `DB::table(…)` walked through the first version of the arm written to stop
 * it.
 *
 * APPEND-ONLY, on `CreditLedgerEntry`'s precedent — and for a different reason
 * worth stating, because the two are easy to conflate. There, an edit corrupts a
 * running balance and cannot be repaired. Here there is no running total; what
 * an edit destroys is **evidence of a charge a carrier already made**. An
 * undelivered-message fee arrives after the send it belongs to, and absorbing it
 * into that send's row would turn a record into a mutable estimate.
 *
 * **Known gap, inherited rather than introduced**: a Query Builder mass update
 * or delete bypasses model events entirely. `audit_log` and `credit_ledger` both
 * carry it; code review is what watches that path.
 *
 * @property int $id
 * @property int $business_id
 * @property MessageCostKind $kind
 * @property int $cost_millicents
 * @property string $currency
 * @property ?int $segments
 * @property ?string $ref_type
 * @property ?int $ref_id
 * @property string $idempotency_key
 */
final class MessageCostEntry extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<MessageCostEntryFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * `business_id` is guarded because it comes from the tenant in context via
     * `BelongsToTenant`, never from a caller's array.
     *
     * `idempotency_key` is guarded too, and that is the load-bearing one: it is
     * what stops a redelivered delivery receipt writing the same carrier cost
     * twice, and a mass-assignable one is a key a caller can vary until the
     * insert stops conflicting.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id', 'idempotency_key'];

    protected static function booted(): void
    {
        self::updating(function (): never {
            throw new LogicException(
                'message_cost_entries is append-only. A cost row records a charge a '
                .'carrier has already made; a later fee is its own row, never an edit '
                .'to the send it followed.'
            );
        });

        self::deleting(function (): never {
            throw new LogicException(
                'message_cost_entries is append-only. Deleting a row makes margin look '
                .'better than it was, in the one table nobody reconciles against an '
                .'external statement.'
            );
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MessageCostKind::class,
            // ⚠️ `integer`, and `BillingTest`'s money lint fails the build on a
            // `*_cents` or `*_millicents` column cast to a float. Money is
            // integer minor units everywhere in this schema (`18` §Money
            // handling); this one counts a smaller minor unit than a cent, and
            // it is still an integer.
            'cost_millicents' => 'integer',
            'segments' => 'integer',
            'ref_id' => 'integer',
            'created_at' => 'immutable_datetime',
        ];
    }
}
