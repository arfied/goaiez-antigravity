<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentGateway;
use Illuminate\Database\Eloquent\Model;

/**
 * Top-up purchase → business. The index a tenantless webhook resolves through.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there —
 * and it is the fifth model in this schema that *names* a tenant and still cannot
 * take the trait, after `ImpersonationSession` (562), `StripeCustomer` (682) and
 * `AuthorizeNetCustomer` (2056). The shape is the same: it is what **establishes**
 * the tenant for a request whose reader has none, so a global scope here would
 * call `Tenancy::idOrFail()` to answer the query whose entire purpose is to
 * discover that id.
 *
 * ⚠️ **THE AUTHORITATIVE COPY IS `credit_purchases`.** This row is an index of it,
 * written in the same transaction by the same service, and if the two ever
 * disagree the tenant-owned row wins. Stated because the opposite guess — that the
 * un-scoped table is the "platform" truth — is the natural one, and acting on it
 * would let a stale pointer credit somebody else's balance.
 *
 * ⚠️ **IT CARRIES NO AMOUNT AND NO PRODUCT, ON PURPOSE.** Everything a settlement
 * needs to decide *what* to credit is behind RLS on the purchase row; this answers
 * only *whose*. A copy of the price here would be a second figure to compare a
 * payment against, in the one table a webhook can read before any tenant exists.
 *
 * @property string $reference
 * @property int $business_id
 * @property PaymentGateway $gateway
 * @property ?string $gateway_transaction_id
 */
final class CreditPurchaseReference extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'credit_purchase_references';

    protected $primaryKey = 'reference';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'gateway' => PaymentGateway::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
