<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\CreditProduct;
use App\Enums\CreditPurchaseStatus;
use App\Enums\CreditTopUpTier;
use App\Enums\PaymentGateway;
use App\Support\Money;
use Database\Factories\CreditPurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One attempt to buy credit (`29` §11.2 row 20's funder, decisions 3102, 3426).
 *
 * ⚠️ **NOT APPEND-ONLY, UNLIKE THE LEDGER IT FEEDS, AND THE DIFFERENCE IS
 * DELIBERATE.** `credit_ledger` records movements that happened; this records an
 * *attempt*, which has a life — opened, charged, settled — and the whole
 * idempotency argument depends on its `status` being claimable by a conditional
 * `UPDATE`. What is append-only is the thing that matters: the ledger row this
 * eventually writes, and a partial unique index makes it impossible to write
 * twice.
 *
 * ⚠️ **`business_id`, `units`, `price_cents` AND EVERY CONFIRMATION COLUMN ARE
 * GUARDED.** `business_id` for `AuditLogEntry`'s reason — it comes from the tenant
 * in context via `BelongsToTenant`, never from a caller's array. The rest because
 * they are what the purchase is *for*: a mass-assignable `units` is a free-credit
 * hole reached through a form request, and a mass-assignable confirmation is a
 * CONFIRM record a caller can write without anybody having confirmed anything.
 * {@see App\Services\Billing\CreditPurchases} assigns all of them explicitly.
 *
 * @property int $id
 * @property int $business_id
 * @property CreditProduct $product
 * @property CreditTopUpTier $tier
 * @property PaymentGateway $gateway
 * @property CreditPurchaseStatus $status
 * @property int $price_cents
 * @property string $currency
 * @property int $units
 * @property int $grant_seed
 * @property string $reference
 * @property ?string $gateway_transaction_id
 * @property string $confirmed_actor
 * @property int $confirmed_amount_cents
 * @property string $confirmation_wording
 * @property Carbon $confirmed_at
 * @property ?Carbon $authorized_at
 * @property ?Carbon $credited_at
 * @property ?string $failure_reason
 */
final class CreditPurchase extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<CreditPurchaseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = [
        'id',
        'business_id',
        'units',
        'grant_seed',
        'price_cents',
        'currency',
        'confirmed_actor',
        'confirmed_amount_cents',
        'confirmation_wording',
        'confirmed_at',
    ];

    /**
     * What the card is charged, as one value rather than two columns.
     *
     * ⚠️ **THE CURRENCY IS PART OF THE AMOUNT** ({@see Money}), which is what
     * makes a settlement comparison meaningful: a notification carrying 5000 of
     * some other currency is not this purchase's price, and comparing bare
     * integers would say it was.
     */
    public function price(): Money
    {
        return Money::of($this->price_cents, $this->currency);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product' => CreditProduct::class,
            'tier' => CreditTopUpTier::class,
            'gateway' => PaymentGateway::class,
            'status' => CreditPurchaseStatus::class,
            'price_cents' => 'integer',
            'units' => 'integer',
            'grant_seed' => 'integer',
            'confirmed_amount_cents' => 'integer',
            'confirmed_at' => 'datetime',
            'authorized_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }
}
