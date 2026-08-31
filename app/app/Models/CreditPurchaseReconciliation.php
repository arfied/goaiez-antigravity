<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\PurchaseReconciliationVerdict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One reconciliation attempt against one purchase (decision 3483's gap).
 *
 * ⚠️ **THE SWEEP'S RECORD, NOT THE PURCHASE'S STATE.** `credit_purchases.status`
 * says where a purchase *is*; a row here says what one read of the gateway
 * *concluded*. The separation is what lets the sweep give up on a transaction
 * without touching a purchase row that {@see App\Services\Billing\CreditPurchases}
 * must still be able to settle — the migration says why that matters more than it
 * looks.
 *
 * ⚠️ **`business_id` AND `credit_purchase_id` ARE GUARDED**, `AuditLogEntry`'s
 * reasoning: the tenant comes from the tenant in context through
 * {@see BelongsToTenant} and never from a caller's array, and a reconciliation
 * pointed at another tenant's purchase is the one write on this table that could
 * do harm.
 *
 * @property int $id
 * @property int $business_id
 * @property int $credit_purchase_id
 * @property PurchaseReconciliationVerdict $verdict
 * @property ?string $gateway_status
 * @property string $detail
 * @property Carbon $attempted_at
 */
final class CreditPurchaseReconciliation extends Model implements TenantScoped
{
    use BelongsToTenant;

    /**
     * ⚠️ `attempted_at` RATHER THAN `created_at`, AND NO `updated_at` AT ALL.
     * An attempt happens at an instant and is never revised; a column named for
     * when the *row* was written would invite one to be.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'credit_purchase_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verdict' => PurchaseReconciliationVerdict::class,
            'attempted_at' => 'immutable_datetime',
        ];
    }
}
