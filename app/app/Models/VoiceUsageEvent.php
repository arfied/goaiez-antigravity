<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoiceUsageKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One duration-billed line of inbound voice — decision 4686.
 *
 * NOT tenant-owned, and on the `TenancyTest` allowlist for `PlacesApiCall`'s
 * reason with a sharper consequence: the calls this meter most needs to see are
 * the ones arriving on a number **no business owns**, so `business_id` is
 * nullable and a null is the answer rather than a gap. A global scope would hide
 * exactly those rows from the query that enforces the ceiling, and row-level
 * security would refuse them outright — a budget that cannot see its own spend
 * is not a budget.
 *
 * APPEND-ONLY IN PRACTICE, on the same table's precedent. Nothing updates a
 * metered second; a ledger that can be edited is not evidence of anything, and
 * this one is what an alert about a strange night is answered from.
 *
 * ⛔ **ONLY `App\Services\Voice\VoiceSpend` MAY TOUCH THIS MODEL**, enforced by
 * an `ArchitectureTest` lint on `CreditLedgerEntry`'s and `MessageCostEntry`'s
 * precedent (625). Every read here is a **cross-tenant** read by construction,
 * so the explicit `business_id` predicate *is* the isolation; a component, a
 * resource or an export reaching the model directly would be one query away from
 * showing one tenant another's call volume.
 *
 * @property-read int $id
 * @property VoiceUsageKind $kind
 * @property string $provider_call_id
 * @property int $billable_seconds
 * @property ?int $business_id
 * @property Carbon $occurred_at
 */
final class VoiceUsageEvent extends Model
{
    /**
     * `created_at` is written by hand and there is no `updated_at` to maintain.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * Usage billed on a given day.
     *
     * ⚠️ **`occurred_at`, NEVER `created_at`.** A webhook redelivered the next
     * morning would otherwise move yesterday's minutes into today's ceiling —
     * `OperatorAlerts::alreadyRang()` records the same trap on `fired_at`, where
     * reading the wrong timestamp fails open silently.
     *
     * @param  Builder<VoiceUsageEvent>  $query
     */
    public function scopeOnDay(Builder $query, Carbon $day): void
    {
        $query->whereBetween('occurred_at', [
            $day->copy()->startOfDay(),
            $day->copy()->endOfDay(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => VoiceUsageKind::class,
            'billable_seconds' => 'integer',
            'business_id' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
