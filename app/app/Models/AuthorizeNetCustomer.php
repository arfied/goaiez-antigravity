<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Authorize.Net customer profile → business. The index a tenantless webhook
 * resolves through.
 *
 * NOT TENANT-OWNED, on the `TenancyTest` allowlist with its reason there —
 * the third model in this schema that *names* a tenant and still cannot take the
 * trait, after `ImpersonationSession` (562) and `StripeCustomer` (682). The
 * shape is identical: it is what **establishes** the tenant for a request whose
 * reader has none, so a global scope here would call `Tenancy::idOrFail()` to
 * answer the query whose entire purpose is to discover that id.
 *
 * ⚠️ **THE AUTHORITATIVE COPY IS ON `subscriptions`.** This row indexes it,
 * written in the same transaction by the same service, and if the two ever
 * disagree the tenant-owned row wins. Stated because the opposite guess — that
 * the un-scoped table is the "platform" truth — is the natural one, and acting
 * on it would make a webhook overwrite a tenant's billing state from a stale
 * pointer.
 *
 * ⚠️ **IT CARRIES THE SUBSCRIPTION ID AS WELL AS THE PROFILE ID, WHICH
 * `StripeCustomer` DOES NOT NEED TO.** Stripe's `customer.subscription.*` events
 * always name their customer; Authorize.Net's subscription events identify the
 * subscription and not reliably the profile. Without the second column, the one
 * class of event that must never be lost — a failed payment — resolves to no
 * tenant at all.
 *
 * @property string $authorize_net_customer_profile_id
 * @property ?string $authorize_net_subscription_id
 * @property int $business_id
 */
final class AuthorizeNetCustomer extends Model
{
    public const null UPDATED_AT = null;

    protected $table = 'authorize_net_customers';

    protected $primaryKey = 'authorize_net_customer_profile_id';

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
