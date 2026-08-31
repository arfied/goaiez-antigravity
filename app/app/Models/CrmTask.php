<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CrmTaskStatus;
use Database\Factories\CrmTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A human follow-up against a customer (DATA-MODEL §5.5, `44` §2).
 *
 * Written and read only through `App\Services\Crm\CrmTasks` — a lint in
 * `tests/Feature/Architecture/CrmTest.php` holds it there. The table spent its
 * whole life in decision 272's shape (model, factory, RLS policy, isolation
 * test, no writer — 1221) until the follow-ups slice gave it one.
 *
 * ⚠️ **`assigned_to` HAS A COLUMN AND NO PICKER, KNOWINGLY** (decision 1324).
 * There is no team, no seats and no membership table anywhere in this schema —
 * `users` carries no `business_id`, and a business reaches its one person
 * through `businesses.owner_user_id` — so `44` §2's assignee picker would hold
 * exactly one name, who is also the default. The column is 272's shape on
 * purpose, stated here so whoever builds team seats finds it already present
 * and already correct rather than reading it as an oversight.
 *
 * @property CrmTaskStatus $status
 * @property ?Carbon $due_at
 * @property ?Carbon $done_at
 * @property ?Carbon $snoozed_until
 * @property ?Carbon $created_at
 */
final class CrmTask extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CrmTaskFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CrmTaskStatus::class,
            'due_at' => 'datetime',
            'done_at' => 'datetime',
            'snoozed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
