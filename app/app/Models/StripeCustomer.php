<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stripe customer → business. The index a tenantless webhook resolves through.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there —
 * and it is the second model in this schema that *names* a tenant and still
 * cannot take the trait, after `ImpersonationSession` (562). The shape is the
 * same: it is what **establishes** the tenant for a request whose reader has
 * none, so a global scope here would call `Tenancy::idOrFail()` to answer the
 * query whose entire purpose is to discover that id.
 *
 * ⚠️ **THE AUTHORITATIVE COPY IS `subscriptions.stripe_customer_id`.** This row
 * is an index of it, written in the same transaction by the same service, and
 * if the two ever disagree the tenant-owned row wins. Stated because the
 * opposite guess — that the un-scoped table is the "platform" truth — is the
 * natural one, and acting on it would make a webhook overwrite a tenant's own
 * billing state from a stale pointer.
 */
final class StripeCustomer extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'stripe_customers';

    protected $primaryKey = 'stripe_customer_id';

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
            'created_at' => 'immutable_datetime',
        ];
    }
}
