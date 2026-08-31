<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataRequestKind;
use App\Enums\DataRequestStatus;
use App\Services\Support\DataRequests;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in `28` §9.5's privacy-request queue.
 *
 * ⚠️ **NO `BelongsToTenant`.** Same reason as {@see TenantDeletionRequest}: the
 * row has to outlive an erasure of the tenant it names, and a staff reader has
 * no tenant. Held by the support gate and a chokepoint naming
 * {@see DataRequests} as the only reader/writer in `app/`.
 *
 * @property-read int $id
 * @property ?int $business_id
 * @property int $business_ref
 * @property DataRequestKind $kind
 * @property DataRequestStatus $status
 * @property ?int $customer_ref
 * @property ?int $deletion_request_id
 * @property CarbonImmutable $due_at
 * @property int $requested_by
 * @property CarbonImmutable $requested_at
 * @property ?int $approved_by
 * @property ?CarbonImmutable $approved_at
 * @property ?int $cancelled_by
 * @property ?CarbonImmutable $cancelled_at
 * @property ?CarbonImmutable $fulfilled_at
 * @property ?CarbonImmutable $refused_at
 * @property ?string $detail
 * @property ?string $outcome_note
 * @property ?array<string, mixed> $result_payload
 */
class DataRequest extends Model
{
    protected $guarded = ['id'];

    /**
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
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DataRequestKind::class,
            'status' => DataRequestStatus::class,
            'due_at' => 'immutable_datetime',
            'requested_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'fulfilled_at' => 'immutable_datetime',
            'refused_at' => 'immutable_datetime',
            'result_payload' => 'array',
        ];
    }
}
