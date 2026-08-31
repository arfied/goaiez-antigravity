<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantDeletionReason;
use App\Services\Tenant\TenantDeletion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to destroy an account: who asked, who agreed, and when it fires.
 *
 * ⚠️ **NO `BelongsToTenant`, AND UNLIKE THE OTHER TWENTY-ONE ALLOWLIST ENTRIES
 * THE REASON IS NOT "IT HAS NO TENANT" — IT IS THAT IT HAS TO OUTLIVE ONE.**
 * `impersonation_sessions` (562) is the closest sibling and stops short of this:
 * it *names* a tenant and cannot take the trait because it establishes the
 * tenant for a reader who has none. This row names a tenant and must still be
 * readable after that tenant has been destroyed, which no tenant-scoped model
 * can be. A scoped model would filter itself to nothing at the exact moment it
 * became the only evidence that the account ever existed.
 *
 * Held where the other platform-scoped stores are held: the admin gate, and a
 * chokepoint lint naming {@see TenantDeletion} as the only reader and writer.
 *
 * ⚠️ **`business_id` GOES NULL AND `business_ref` DOES NOT.** Read `business_ref`
 * for "which account was this", always. Read `business_id` only when you need a
 * live row, and expect null — after execution there is nothing to point at, and
 * that null is the record of success rather than a missing value.
 *
 * @property-read int $id
 * @property ?int $business_id
 * @property int $business_ref
 * @property TenantDeletionReason $reason
 * @property ?string $detail
 * @property int $requested_by
 * @property CarbonImmutable $requested_at
 * @property ?int $confirmed_by
 * @property ?CarbonImmutable $confirmed_at
 * @property ?CarbonImmutable $executes_at
 * @property ?int $cancelled_by
 * @property ?CarbonImmutable $cancelled_at
 * @property ?string $cancellation_reason
 * @property ?CarbonImmutable $executed_at
 */
class TenantDeletionRequest extends Model
{
    /**
     * Every column is state that {@see TenantDeletion} owns.
     *
     * Guarded wholesale rather than per-column: this model is written through
     * one service and a request body must never reach `executes_at`, which is
     * the column that decides when an account stops existing.
     */
    protected $guarded = ['id'];

    /**
     * The account, while there still is one.
     *
     * ⚠️ Null after execution, by design — see the class docblock. A caller
     * that needs the subject regardless wants `business_ref`.
     *
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * `immutable_datetime`, matching {@see ImpersonationSession} rather than the
     * plain `datetime` most of this schema uses. These five timestamps are the
     * record of an irreversible act, and a mutable Carbon handed to a caller can
     * be advanced in place by something as ordinary as `->addDays()` on what
     * looked like a copy.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reason' => TenantDeletionReason::class,
            'requested_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'executes_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'executed_at' => 'immutable_datetime',
        ];
    }
}
